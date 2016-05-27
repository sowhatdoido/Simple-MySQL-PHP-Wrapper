<?php

class DB {
    private static $_instance = false;
    private static $_config = array(
        'host' => DB_HOST,
        'database' => DB_NAME,
        'username' => DB_USER,
        'password' => DB_PASS
    );
    private static $_keywords = array('NOW()');
    
    public static function configure($config){
        self::$_config = $config;
    }
    
    public static function initialize(){
        try {
			self::$_instance = new PDO('mysql:host='.self::$_config['host'].';dbname='.self::$_config['database'].';charset=utf8', self::$_config['username'], self::$_config['password'], array(PDO::ATTR_EMULATE_PREPARES => false, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
		} catch(PDOException $e){
			throw new Exception('MYSQL :: database object could not establish a connection : { ' . $e->getMessage() . ' }');
			die();
		}
    }
    
    private static function _checkConnection(){
        if(!self::$_instance){
            throw new Exception('Database has not been intialized. Please call Database::configure($array), followed by Database::initialize().');
        }
    }
    
    public static function insert($data, $table){
        self::_checkConnection();
        
        if(empty($data)){
            throw new Exception("Data is required for DB Insert");
        }
        
        foreach($data as $k => $v){
            $keys[] = "`{$k}`";
            if(in_array($v, self::$_keywords, true)){
                $placeholders[] = $v;
            } else {
                $placeholders[] = "?";
                $values[] = $v;
            }            
        }
        $keys = implode(",", $keys);
        $placeholders = implode(",", $placeholders);
        
        $q = "INSERT INTO `". self::$_config['database'] ."`.`{$table}` ({$keys}) VALUES ({$placeholders})";

        $st = self::$_instance->prepare($q);
        $st->execute($values);
        
        return self::$_instance->lastInsertId();
    }
    
    public static function update($data, $table, $where = null, $where_values = null, $disable_guard = false){
        self::_checkConnection();
        
        if(empty($data)){
            throw new Exception("Data is required for DB Update");
        }
        
        if(is_null($where) && !$disable_guard){
            throw new Exception("You have not indicated a where statement. If this is intentional, please set the `disable_guard` parameter to true.");
        }
        
        foreach ($data as $k => $v){
            if(in_array($v, self::$_keywords, true)){
                $update[] = "`{$k}` = {$v}";
            } else {
                $update[] = "`{$k}` = ?";
                $values[] = $v;
            }
        }
        
        $update = implode(",", $update);
        $values = array_merge($values, $where_values);
        
        $q = "UPDATE `". self::$_config['database'] ."`.`{$table}` SET {$update}";
        if(!is_null($where)) $q .= " WHERE {$where}";
        
        $st = self::$_instance->prepare($q);
        $st->execute($values);
        
        return;
    }
    
    public static function delete($table, $where, $values){
        self::_checkConnection();
        
        // Unlike update, I'm not going to allow for the deletion of data without a where clause.
        $q = "DELETE FROM `". self::$_config['database'] ."`.`{$table}` WHERE {$where}";
        $st = self::$_instance->prepare($q);
        $st->execute($values);
        
        return;
    }
    
    public static function select($what, $table, $where = null, $values = null, $options = null){
        self::_checkConnection();
        
        $q = "SELECT ";
        $q .= (is_array($what))? implode(',', $what) : $what;
        $q .= " FROM `" . self::$_config['database'] . "`.`$table`"; 
        if(!is_null($where)){
            $q .= " WHERE " . $where;
        }
        
        if(!is_null($options)){
            $q .= " {$options}"; //For things like SORT BY/ORDER BY
        }
        
        $st = self::$_instance->prepare($q);
        $st->execute($values);
        
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public static function selectOne($what, $table, $where = null, $values = null, $options = null){
        self::_checkConnection();
        
        if($return = self::select($what, $table, $where, $values, $options))
            return ($return[0]);
        else
            return false;
    }
    
    public static function raw($query, $values = null){
        return self::runQuery($query, $values);
    }
    
    public static function runQuery($query, $values = null) {
        self::_checkConnection();
        
        $st = self::$_instance->prepare($query);
		$st->execute($values);
        $cmd = explode(' ', $query);
        
        if(strtolower($cmd[0]) === "select") //Only return data on select statements
		  return $st->fetchAll(PDO::FETCH_ASSOC);
    }
}