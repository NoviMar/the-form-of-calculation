<?php
/* Small DataBase Handler */

/* Root exception class for sdbh */
class sdbh_exception extends Exception {
}

/* InnoDB запор */
class sdbh_deadlock_exception extends sdbh_exception {
}

/* Потеря соединения */
class sdbh_lost_connection_exception extends sdbh_exception {
}

class sdbh_tables_exception extends sdbh_exception {
}

class sdbh {
    function __construct($force_master = false){
        $this->port = 3306;
        $this -> host = 'localhost';
        $this -> dbname = 'test_a25';
        $this -> user = 'root';
        $this -> pass = 'root';
        $mysql_conn = mysqli_connect($this -> host, $this -> user, $this -> pass, $this -> dbname, $this->port);

        $this -> sql_read = $mysql_conn;
        $this -> sql_write = false;
    }


    protected function get_connection($query){
        if ($this -> sql_read AND stripos(substr($query,0,10),'select')!==FALSE){
            $sql = $this -> sql_read;
        }else{
            if ($this -> sql_write){
                $sql = $this -> sql_write;
            }else{
                //$sql = $this -> sql_write = mysqli_connect($this -> host, $this -> user, $this -> pass, $this -> dbname, 3306);
                //$sql -> query('SET NAMES "utf8mb4" COLLATE "utf8mb4_unicode_ci"');
                $sql = $this -> sql_read;
            }
        }
        return $sql;
    }

    /**
     * Query function, throwing exceptions on errors
     * @param query - query string
     * @return query result from mysqli query() function
     * @throw sdbh_deadlock_exception in case of innodb deadlock
     * @throw sdbh_exception in case of other query errors
     */
    public function query_exc($query){

        $this -> sql = $this -> get_connection($query);

        $q = $this -> sql -> query($query);
        $err = $this -> sql -> errno;
        if (
            $err == 1213 // Deadlock
            || $err == 1205 // Lock timeout
        ) {
            throw new sdbh_deadlock_exception("Deadlock Query:".$query);
        }

        if ($err == 2006 || $err == 2002){
            $this -> sql -> close();
            $this -> __construct();
            throw new sdbh_lost_connection_exception("Lost connection!");
        }

        if ($err == 3032){
            $this -> sql -> close();
            $this -> __construct(true);
            throw new sdbh_dead_replicas("Dead replicas!");
        }

        if ($this -> sql -> errno == 1146 && (strpos($this -> sql -> error, 'EA_') || strpos($this -> sql -> error, 'Urls_') || strpos($this -> sql -> error, 'Z_LM_'))){
            throw new sdbh_tables_exception("Query failed! No EA or Urls or Z_LM tables! Query: ".$query);
        }

        if ($this -> sql -> errno == 1064){
            throw new sdbh_exception("Query failed! Full Query:\n".$query);
        }

        if(!$q){
            throw new sdbh_exception("Query failed! ".$this -> sql -> errno.": ".$this -> sql -> error." Query:\n".$query);
        }
        return $q;
    }


    public function query_ds_exc($query){
        while(True) try{
            $q = $this -> query_exc($query);
            break;
        }catch(sdbh_tables_exception $ex){
            $q = false;
            break;
        }catch(sdbh_deadlock_exception $ex){
            continue;
        }catch(sdbh_lost_connection_exception $ex){
            continue;
        }catch(sdbh_dead_replicas $ex){
            continue;
        }
        return $q;
    }
    /**
     * Old, compatibility function to execute plain-text queries in
     * deadlock-safe manner.
     * @param query - query text
     * @return many interesting things :)
     */
    public function make_query($query, $reconnect = false){
        $this -> sql = $this -> get_connection($query);
        $r=$this->query_ds_exc($query, $reconnect);
        if(mysqli_errno($this->sql)){ return mysqli_error($this->sql);
        }elseif(stristr(substr($query,0,10),'select')!==false){return $this->get_all_assoc($r);}
        return mysqli_affected_rows($this->sql);
    }


    public function getDBName(){
        return $this -> dbname;
    }

    /**
     * Produces associative array by query result
     * @param $q - query answer variable from mysqli query function
     * @return array of rows, each being an associative array
     */
    public function get_all_assoc($q){
        if(!$q){
            return array();
        }
        $ans = array();
        while($row = $q -> fetch_assoc()){
            $ans[] = $row;
        }
        return $ans;
    }

    /**
     * Proxy to mysqli string escaping function
     */
    public function escape_string($str){
        $this -> sql = $this -> get_connection($str);
        return $this -> sql -> escape_string($str);
    }
    /**
     * Casts to string and separates with commas
     */
    public function csv_it($arr){
        $str = "";
        $first = True;
        foreach($arr as $a){
            if($first){
                $first = False;
            }else{
                $str .= ", ";
            }
            $str .= $a;
        }
        return $str;
    }

    protected function make_str($array, $sep, $table) {
        if (is_array($array) && !empty($array)) {
            $str = '';
            $first = true;
            foreach ($array as $field => $value) {
                if ($first) {
                    $first = false;
                } else {
                    $str .= " $sep ";
                }
                $escaped_value = $this->escape_string($value);
                $str .= "`$table`.`$field`='$escaped_value'";
            }
            return $str;
        } else {
            return '1'; // Возвращаем 1, чтобы избежать синтаксической ошибки в SQL-запросе
        }
    }

    /**
     * Executes UPDATE query, generated by given parameters. All data
     * values are safely escaped before inserting. Table and field names
     * are !!NOT!! escaped.
     * @param $tbl_name - table name
     * @param $update_array - array("field" => new_value)
     * @param $select_array - array("field" => conditional_value)
     * @param $deadlock_up - raise exception on deadlock or automatically restart
     * @return mysqli affected rows
     */
    function update_rows(
        $tbl_name,
        $update_array,
        $select_array,
        $deadlock_up = False
    ){
        while(True) try{
            $this->query_exc("UPDATE `$tbl_name` SET
				".$this->make_str($update_array,',',$tbl_name)." WHERE
				".$this->make_str($select_array,'AND',$tbl_name)
            );
            break;
        }catch(sdbh_tables_exception $e){
            break;
        }catch(sdbh_deadlock_exception $e){
            if($deadlock_up){
                throw $e;
            }else{
                continue;
            }
        }catch(sdbh_lost_connection_exception $e){
            continue;
        }
        $a_rows = mysqli_affected_rows($this->sql);
        return $a_rows;
    }

    /**
     * By given parameters generates SELECT query, executes it and returns
     * answer in array. Data values in select_array and $from, $amount
     * fields are escaped and intval-ed respectively, others !!are not!!.
     * @param $tbl_name - table name
     * @param $select_array - field - value condition array
     * @param $from, $amount - limits
     * @param $order_by - field to sort anser by
     * @param $order - "DESC" or Null
     * @param $deadlock_up - throw deadlock_exception out or restart query internaly
     * @param $lock_mode - LISH - LOCK IN SHARE MODE, FU - FOR UPDATE or Null - nothing
     * @return query result as array of rows, each an associative array
     */
    public function mselect_rows($tbl_name, $select_array, $from, $amount, $order_by, $order = null, $deadlock_up = false, $lock_mode = null) {
        if (is_null($lock_mode)) {
            $lock_str = "";
        } else if ($lock_mode == "LISH") {
            $lock_str = "LOCK IN SHARE MODE";
        } else if ($lock_mode == "FU") {
            $lock_str = "FOR UPDATE";
        } else {
            throw new sdbh_exception("Unknown lock mode $lock_mode");
        }

        $where_clause = $this->make_str($select_array, 'AND', $tbl_name);
        if ($where_clause !== '1') {
            $where_clause = "WHERE " . $where_clause;
        } else {
            $where_clause = "";
        }

        if (is_null($order)) {
            $order = "";
        }

        while (true) {
            try {
                $query = $this->query_exc("SELECT * FROM `$tbl_name` $where_clause ORDER BY `$order_by` $order LIMIT " . intval($from) . ", " . intval($amount) . " $lock_str");
                break;
            } catch (sdbh_tables_exception $e) {
                $query = false;
                break;
            } catch (sdbh_deadlock_exception $e) {
                if ($deadlock_up) {
                    throw $e;
                } else {
                    continue;
                }
            } catch (sdbh_lost_connection_exception $e) {
                continue;
            }
        }
        return $this->get_all_assoc($query);
    }

    /**
     * Deletes rows from table
     * @param $tbl_name - table name
     * @param $delete_array - array of key => value conditions
     * @param $deadlock_up - throw deadlock exception or restart query automatically
     * @return affected rows
     */
    function delete_rows($tbl_name, $delete_array, $deadlock_up = False){
        while(True) try{
            $this->query_exc("DELETE FROM $tbl_name WHERE ".$this->make_str($delete_array,'AND',$tbl_name));
            break;
        }catch(sdbh_deadlock_exception $e){
            if($deadlock_up){
                throw $e;
            }else{
                continue;
            }
        }catch(sdbh_lost_connection_exception $e){
            continue;
        }
        return $this -> sql -> affected_rows;
    }

    /**
     * Inserts a set of rows into a table, ignoring failures
     * @param $tbl_name - table name
     * @param $rows - array of associative arrays like ("column" => "value"), keys are obtained from the first record
     * @param deadlock_up - throw deadlock exception or restart query automatically
     * @return number of rows inserted
     */
    function insert_rows($tbl_name, $rows, $deadlock_up = False){
        if(empty($rows)){
            return 0;
        }
        $val = "";
        $first = True;
        foreach($rows as $row){
            if($first){
                $first = False;
                $keys = array_keys($row);
            }else{
                $val .= ",";
            }
            $val .= "(";
            $subfirst = True;
            foreach($keys as $k){
                if($subfirst){
                    $subfirst = False;
                }else{
                    $val .= ",";
                }
                $val .= '"' . $this -> escape_string($row[$k]) . '"';
            }
            $val .= ")";
        }
        $keys_csv = "";
        $first = True;
        foreach($keys as $k){
            if($first){
                $first = False;
            }else{
                $keys_csv .= ",";
            }
            $keys_csv .= "`$k`";
        }
        while(True) try{
            $this -> query_exc("INSERT IGNORE INTO `$tbl_name` ($keys_csv) VALUES $val");
            break;
        }catch(sdbh_tables_exception $e){
            return false;
        }catch(sdbh_deadlock_exception $e){
            if($deadlock_up){
                throw $e;
            }else{
                continue;
            }
        }catch(sdbh_lost_connection_exception $e){
            continue;
        }
        return $this -> sql -> affected_rows;
    }

    // START TRANSACTION, COMMIT, ROLLBACK convinience functions
    function start_transaction(){
        $this -> query_exc("START TRANSACTION");
    }
    function commit(){
        $this -> query_exc("COMMIT");
    }
    function rollback(){
        $this -> query_exc("ROLLBACK");
    }

    /**
     * Get user row from table by user name (sorry, it's old and eccentric)
     * @param $tbl_name - table name to select from
     * @param $username - user name (seriously!). Restarts transaction in case
     * of a deadlock.
     * @return associative array with user row data
     */
    public function get_user($tbl_name, $username){
        while(True) try{
            $q = $this -> query_exc("SELECT * FROM `$tbl_name` WHERE `username`='".
                $this -> escape_string($username)."' LIMIT 1");
            $qq = $this -> get_all_assoc($q);
            break;
        }catch(sdbh_deadlock_exception $e){
            continue;
        }catch(sdbh_lost_connection_exception $e){
            continue;
        }
        if(empty($qq[0])){
            return False;
        }else{
            return $qq[0];
        }
    }

    /**
     * Counts rows in a table, matching specified condition
     * @param $tbl_name - table name
     * @param $select array - array like (field => value) with select conditions
     * @param deadlock_up - push deadlock exception out or restart silently
     * @return number of matching rows inside array('0' => array(0 => N))
     * for historical reasons
     */
    public function count_rows($tbl_name, $select_array, $deadlock_up = False){
        while(True) try{
            $query = $this->query_exc("SELECT COUNT(*) as `0` FROM `$tbl_name`
				WHERE ".$this->make_str($select_array,'AND',$tbl_name));
            break;
        }catch(sdbh_deadlock_exception $e){
            if($deadlock_up){
                throw $e;
            }else{
                continue;
            }
        }catch(sdbh_lost_connection_exception $e){
            continue;
        }
        return $this->get_all_assoc($query);
    }

    /**
     * Inserts a row into database, automatically restarting deadlocks.
     * @param $tbl_name - target table name
     * @param insert array - array of fields and values
     * @return insert_id or false
     */
    public function insert_row($tbl_name, $insert_array){
        $N = $this -> insert_rows($tbl_name, array($insert_array));
        if($N){
            return $this -> sql -> insert_id;
        }else{
            return False;
        }
    }

    /* Make sure it's needed before enabling: possible SQL injection in $afields
    public function import_csv(
        $table, 		// Имя таблицы для импорта
        $afields, 		// Массив строк - имен полей таблицы
        $filename, 	 	// Имя CSV файла, откуда берется информация
                    // (путь от корня web-сервера)
        $delim=',',  		// Разделитель полей в CSV файле
        $enclosed='"',  	// Кавычки для содержимого полей
        $escaped='\\', 	 	// Ставится перед специальными символами
        $lineend='\\r\\n',   	// Чем заканчивается строка в файле CSV
        $hasheader=TRUE){  	// Пропускать ли заголовок CSV

    if($hasheader) $ignore = "IGNORE 1 LINES ";
    else $ignore = "";
    $q_import =
    "LOAD DATA LOCAL INFILE '".
        $this -> escape_string($_SERVER['DOCUMENT_ROOT'].$filename).
    "' INTO TABLE ".$table." ".
    "FIELDS TERMINATED BY '".$delim."' ENCLOSED BY '".$enclosed."' ".
    "    ESCAPED BY '".$escaped."' ".
    "LINES TERMINATED BY '".$lineend."' ".
    $ignore.
    "(".implode(',', $afields).")"
    ;
        while(True) try{
            $query=$this->query_exc($q_import);
            break;
        }catch(sdbh_deadlock_exception $e){
            continue;
        }
        return $q_import;
    }*/

    /*Создает индивидуальные таблицы пользователей
    Принимаемые значения:
        - название таблицы $tbl_name {Z_LM, EA, Detailed_EA, Urls}
        - id юзера $user*/
    public function create_tbl($tbl_name, $user){
        $create = false;
        if ($tbl_name == 'Z_LM') {
            $query = "CREATE TABLE IF NOT EXISTS `Z_LM_$user` (
			`id` int(11) NOT NULL AUTO_INCREMENT,
			`list_id` int(11) NOT NULL,
			`email` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
			`state` enum('active','unsubscribed','bounced','inactive','unconfirmed','complained') COLLATE utf8_unicode_ci NOT NULL DEFAULT 'active',
			`merge_1` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
			`merge_2` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
			`merge_3` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
			`merge_4` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
			`merge_5` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
			`merge_6` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
			`merge_7` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
			`merge_8` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
			`merge_9` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
			`merge_10` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
			`merge_11` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
			`merge_12` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
			`merge_13` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
			`merge_14` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
			`merge_15` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
			`optin_time` datetime NOT NULL,
			`bounce_time` datetime NOT NULL,
			`unsub_time` datetime NOT NULL,
			`lastedit_time` datetime NOT NULL,
			`gender` enum('m', 'f', 'n') COLLATE utf8_unicode_ci NOT NULL DEFAULT 'n',
			`source` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
			`device` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
			`rating` int(2) COLLATE utf8_unicode_ci NOT NULL DEFAULT '50',
			`region` int(3) COLLATE utf8_unicode_ci NOT NULL,
			PRIMARY KEY (`id`),
			UNIQUE KEY `В базах уникальные пользователи` (`list_id`,`email`),
			KEY `list_id` (`list_id`,`state`),
			KEY `emails` (`email`),
			KEY `state` (`state`),
			KEY `gender` (`gender`),
			KEY `optin_time` (`optin_time`),
			KEY `bounce_time` (`bounce_time`),
			KEY `unsub_time` (`unsub_time`),
			KEY `lastedit_time` (`lastedit_time`),
			KEY `rating` (`rating`),
			KEY `source` (`source`),
			KEY `region` (`region`)
			) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1;";
            $create = $this->query_exc($query);
        }
        if ($tbl_name == 'EA') {
            $query = "CREATE TABLE IF NOT EXISTS `EA_$user` (
		    `id` bigint(20) NOT NULL AUTO_INCREMENT,
		    `member_id` int(11) NOT NULL,
		    `campaign_id` int(11) NOT NULL,
		    `list_id` int(11) NOT NULL,
		    `external_campaign_id` varchar(255) NOT NULL,
		    `campaign_channel_id` varchar(255) NOT NULL DEFAULT 'email',
		    `bounce_code` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
		    `bounce_category` enum('hrd','sft','blk','spm') COLLATE utf8_unicode_ci NOT NULL,
		    `bounce_reason` text COLLATE utf8_unicode_ci NOT NULL,
		    `email` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
		    `domain` varchar( 50 ) COLLATE utf8_unicode_ci NOT NULL ,
		    `hash` char(32) COLLATE utf8_unicode_ci NOT NULL,
		    `delivered` tinyint(1) NOT NULL DEFAULT '0',
		    `bounced` tinyint(1) NOT NULL DEFAULT '0',
		    `clicked` tinyint(1) NOT NULL DEFAULT '0',
		    `opened` tinyint(1) NOT NULL DEFAULT '0',
		    `unsubscribed` tinyint(1) NOT NULL DEFAULT '0',
		    `preview` tinyint(1) NOT NULL DEFAULT '0',
		    `forward` tinyint(1) NOT NULL DEFAULT '0',
		    `vcard` tinyint(1) NOT NULL DEFAULT '0',
		    `sent_time` datetime NOT NULL,
		    `delivery_time` datetime NOT NULL,
		    `unsub_time` datetime NOT NULL,
		    `bounce_time` datetime NOT NULL,
		    `open_time` datetime NOT NULL,
		    `click_time` datetime NOT NULL,
		    `experiment_id` int(11) NOT NULL,
		    `batch_id` tinyint(2) NOT NULL,
		    `subject_id` mediumint(6) NOT NULL,
		    PRIMARY KEY (`id`),
		    UNIQUE KEY `unique email` (`campaign_id`,`member_id`),
		    KEY `campaign_id` (`campaign_id`),
		    KEY `external_campaign_id` (`external_campaign_id`),
		    KEY `member_id` (`member_id`),
		    KEY `list_id` (`list_id`),
		    KEY `hash` (`hash`),
		    KEY `emails` (`email`),
		    KEY `domain` (`domain`),
		    KEY `clicked` (`clicked`),
		    KEY `opened` (`opened`),
		    KEY `bounced` (`bounced`),
		    KEY `unsubscribed` (`unsubscribed`),
		    KEY `preview` (`preview`),
		    KEY `forward` (`forward`),
		    KEY `vcard` (`vcard`),
		    KEY `sent_time` (`sent_time`)
		    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1;";
            $create = $this->query_exc($query);
        }
        if ($tbl_name == 'MT_EA') {
            $query = "CREATE TABLE IF NOT EXISTS `MT_EA_$user` (
		    `id` bigint(20) NOT NULL AUTO_INCREMENT,
		    `user_id` int(11) NOT NULL,
		    `campaign_id` int(11) NOT NULL,
		    `timestamp` int(11) NOT NULL,
		    `bounce_code` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
		    `bounce_reason` text COLLATE utf8_unicode_ci NOT NULL,
		    `email` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
		    `domain` varchar( 50 ) COLLATE utf8_unicode_ci NOT NULL ,
		    `hash` char(32) COLLATE utf8_unicode_ci NOT NULL,
		    `delivered` tinyint(1) NOT NULL DEFAULT '0',
		    `bounced` tinyint(1) NOT NULL DEFAULT '0',
		    `clicked` tinyint(1) NOT NULL DEFAULT '0',
		    `opened` tinyint(1) NOT NULL DEFAULT '0',
		    `unsubscribed` tinyint(1) NOT NULL DEFAULT '0',
		    `preview` tinyint(1) NOT NULL DEFAULT '0',
		    `forward` tinyint(1) NOT NULL DEFAULT '0',
		    `vcard` tinyint(1) NOT NULL DEFAULT '0',
		    `sent_time` datetime NOT NULL,
		    `delivery_time` datetime NOT NULL,
		    `bounce_time` datetime NOT NULL,
		    `open_time` datetime NOT NULL,
		    PRIMARY KEY (`id`),
		    UNIQUE KEY `timestamp` (`campaign_id`,`timestamp`, `email`),
		    KEY `campaign_id` (`campaign_id`),
		    KEY `hash` (`hash`),
		    KEY `emails` (`email`),
		    KEY `domain` (`domain`),
		    KEY `clicked` (`clicked`),
		    KEY `opened` (`opened`),
		    KEY `bounced` (`bounced`),
		    KEY `unsubscribed` (`unsubscribed`),
		    KEY `preview` (`preview`),
		    KEY `forward` (`forward`),
		    KEY `vcard` (`vcard`)
		    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1;";
            $create = $this->query_exc($query);
        }
        if ($tbl_name == 'Detailed_EA') {
            $query = "CREATE TABLE IF NOT EXISTS `Detailed_EA_$user` (
			`id` bigint(20) NOT NULL AUTO_INCREMENT,
			`campaign_id` int(11) NOT NULL,
			`external_campaign_id` varchar(255) NOT NULL,
			`email` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
			`email_id` bigint(20) NOT NULL,
			`action` enum('opened','clicked','preview','unsub','forward') COLLATE utf8_unicode_ci NOT NULL,
			`ip` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
			`region` int(3) COLLATE utf8_unicode_ci NOT NULL,
			`OS` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
			`browser` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
			`webservice` varchar(10) COLLATE utf8_unicode_ci NOT NULL,
			`user_agent` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
			`referer` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
			`language` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
			`device` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
			PRIMARY KEY (`id`),
			UNIQUE KEY `unique` (`email_id`,`action`),
			KEY `search` (`campaign_id`,`email`),
			KEY `OS` (`OS`),
			KEY `browser` (`browser`),
			KEY `webservice` (`webservice`),
			KEY `language` (`language`),
			KEY `campaign_id` (`campaign_id`),
			KEY `device` (`device`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";
            $create = $this->query_exc($query);
        }
        if ($tbl_name == 'Urls') {
            $query = "CREATE TABLE IF NOT EXISTS `Urls_$user` (
		    `id` bigint(20) NOT NULL AUTO_INCREMENT,
		    `campaign_id` int(11) NOT NULL,
		    `external_campaign_id` varchar(255) NOT NULL,
		    `list_id` int(11) NOT NULL,
		    `url_hash` char(32) CHARACTER SET latin1 NOT NULL,
		    `url` text COLLATE utf8_unicode_ci NOT NULL,
		    `clicks` int(11) NOT NULL DEFAULT '0',
		    PRIMARY KEY (`id`),
		    KEY `url_hash` (`url_hash`),
		    KEY `list_id` (`list_id`),
		    KEY `campaign_id` (`campaign_id`),
		    KEY `external_campaign_id` (`campaign_id`),
		    KEY `clicks` (`clicks`)
		    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";
            $create = $this->query_exc($query);
        }

        return $create;
    }
}
