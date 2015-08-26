<?php
class MysqlDB {

   protected $_mysql;
   protected $_where = array();
   protected $_query;
   protected $_paramTypeList;
   protected $_orderBy = array();
   protected $_groupBy;
   protected $_wheres = array();
   protected $_whereBetween = array();

   public function __construct() {
      include($_SERVER["DOCUMENT_ROOT"] . "/ghanabuys/classes/config.php");
      $this->_mysql = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_DATABASE) or die('There was a problem connecting to the database');
   }

   /**
    *
    * @param string $query Contains a user-provided select query.
    * @param int $numRows The number of rows total to return.
    * @return array Contains the returned rows from the query.
    */
   public function query($query) 
   {
      $this->_query = filter_var($query, FILTER_SANITIZE_STRING);

      $stmt = $this->_prepareQuery();
      $stmt->execute();
      $results = $this->_dynamicBindResults($stmt);
      return $results;
   }

    public function fetchWithLike($tableName, $columnName, $string)
    {
        $query = "SELECT * FROM $tableName WHERE $columnName LIKE '%$string%'";
        $result = mysqli_query($this->_mysql, $query);
        while($row = mysqli_fetch_assoc($result))
        {
             $rows[] = $row;
        }
        if(isset($rows))
            return $rows;
    }

   /**
    * A convenient SELECT * function.
    *
    * @param string $tableName The name of the database table to work with.
    * @param int $numRows The number of rows total to return.
    * @return array Contains the returned rows from the select query.
    */
   public function get($tableName, $numRows = NULL)
   {

      $this->_query = "SELECT * FROM $tableName";
      $stmt = $this->_buildQuery($numRows);
      $stmt->execute();
      
      $results = $this->_dynamicBindResults($stmt);
      return $results;
   }

    /**
     * A convenient SELECT with comma separated rows function.
     *
     * @param string $tableName The name of the database table to work with.
     * @param string $rows Comma separated string of rows or row to return
     * @param int $numRows The number of rows total to return.
     * @param boolean $distinct Whether to return distinct values
     * @return array Contains the returned rows from the select query.
     */
    public function getRows($tableName, $rows, $distinct = False, $numRows = NULL)
    {
        if($distinct)
            $this->_query = "SELECT DISTINCT $rows FROM $tableName";
        else
            $this->_query = "SELECT $rows FROM $tableName";

        $stmt = $this->_buildQuery($numRows);
        $stmt->execute();

        $results = $this->_dynamicBindResults($stmt);
        return $results;
    }





   /**
    *
    * @param <string $tableName The name of the table.
    * @param array $insertData Data containing information for inserting into the DB.
    * @return boolean Boolean indicating whether the insert query was completed succesfully.
    */
   public function insert($tableName, $insertData, $getId = false)
   {
      $this->_query = "INSERT into $tableName";
      $stmt = $this->_buildQuery(NULL, $insertData);
      $stmt->execute();

      if ($stmt->affected_rows)
          if($getId == true)
            return $stmt->insert_id;
         return true;
   }

   /**
    * Update query. Be sure to first call the "where" method.
    *
    * @param string $tableName The name of the database table to work with.
    * @param array $tableData Array of data to update the desired row.
    * @return boolean
    */
   public function update($tableName, $tableData) 
   {
      $this->_query = "UPDATE $tableName SET ";

      $stmt = $this->_buildQuery(NULL, $tableData);
      $stmt->execute();

      if ($stmt->affected_rows)
         return true;
   }

   /**
    * Delete query. Call the "where" method first.
    *
    * @param string $tableName The name of the database table to work with.
    * @return boolean Indicates success. 0 or 1.
    */
   public function delete($tableName) {
      $this->_query = "DELETE FROM $tableName";

      $stmt = $this->_buildQuery();
      $stmt->execute();

      if ($stmt->affected_rows)
         return true;
   }


   /**
    * This method allows you to specify a WHERE statement for SQL queries.
    *
    * @param string $whereProp A string for the name of the database field to update
    * @param mixed $whereValue The value for the field.
    */
   public function where($whereProp, $whereValue) 
   {

      if(count($this->_where) == 0) {
        $this->_where[$whereProp] = $whereValue;
      } else {
          $where = array($whereProp=>$whereValue);
          $this->_wheres = array_merge($this->_wheres,$where);

      }
   }

    public function whereBetween($column, $from, $to)
    {
        $this->_whereBetween[0] = $column;
        $this->_whereBetween[1] = $from;
        $this->_whereBetween[2] = $to;
    }

    /**
     * This method allows you to specify how the result should be sorted
     *
     * @param string $by the column to order
     * @param string $order whether ascending 'ASC' or descending 'DESC'
     */
    public function orderBy($by, $order)
    {
        $this->_orderBy[$by] = $order;
    }

    /**
     * This method allows you to specify how result should be grouped
     * @param string $by the column to group
     */
    public function groupBy($by)
    {
        $this->_groupBy = $by;
    }

    /**
    * This method is needed for prepared statements. They require
    * the data type of the field to be bound with "i" s", etc.
    * This function takes the input, determines what type it is,
    * and then updates the param_type.
    *
    * @param mixed $item Input to determine the type.
    * @return string The joined parameter types.
    */
   protected function _determineType($item) 
   {
      switch (gettype($item)) {
         case 'string':
            return 's';
            break;

         case 'integer':
            return 'i';
            break;

         case 'blob':
            return 'b';
            break;

         case 'double':
            return 'd';
            break;
      }
   }

   /**
    * Abstraction method that will compile the WHERE statement,
    * any passed update data, and the desired rows.
    * It then builds the SQL query.
    *
    * @param int $numRows The number of rows total to return.
    * @param array $tableData Should contain an array of data for updating the database.
    * @return object Returns the $stmt object.
    */
   protected function _buildQuery($numRows = NULL, $tableData = false) 
   {
      $hasTableData = null;
      if (gettype($tableData) === 'array') {
         $hasTableData = true;
      }

      // Did the user call the "where" method?
      if (!empty($this->_where)) {
         $keys = array_keys($this->_where);
         $where_prop = $keys[0];
         $where_value = $this->_where[$where_prop];

         // if update data was passed, filter through
         // and create the SQL query, accordingly.
         if ($hasTableData) {
            $i = 1;
				$pos = strpos($this->_query, 'UPDATE');
				if ( $pos !== false) {
					foreach ($tableData as $prop => $value) {
						// determines what data type the item is, for binding purposes.
						$this->_paramTypeList .= $this->_determineType($value);

						// prepares the rest of the SQL query.
						if ($i === count($tableData)) {
                            $this->_paramTypeList .= $this->_determineType($where_value);
							$this->_query .= $prop . " = ? WHERE " . $where_prop . "= ? "; //. $where_value;
						} else {
							$this->_query .= $prop . ' = ?, ';
						}

						$i++;
					}
				}
         } else {
            // no table data was passed. Might be SELECT statement.
            $this->_paramTypeList = $this->_determineType($where_value);
            $this->_query .= " WHERE " . $where_prop . "= ?";
         }
          /**
           * check if where value has been called multiple time and then prepare the query
           */
          if(count($this->_wheres) > 0) {
              foreach($this->_wheres as $props => $values) {
                  $this->_paramTypeList .= $this->_determineType($values);
                  $this->_query .= " AND ". $props . "= ? ";
              }
          }


      }

      // Determine if is INSERT query
      if ($hasTableData) {
         $pos = strpos($this->_query, 'INSERT');

         if ($pos !== false) {
            //is insert statement
            $keys = array_keys($tableData);
            $values = array_values($tableData);
            $num = count($keys);

            // wrap values in quotes
            foreach ($values as $key => $val) {
               $values[$key] = "'{$val}'";
               $this->_paramTypeList .= $this->_determineType($val);
            }

            $this->_query .= '(' . implode($keys, ', ') . ')';
            $this->_query .= ' VALUES(';
            while ($num !== 0) {
               ($num !== 1) ? $this->_query .= '?, ' : $this->_query .= '?)';
               $num--;
            }
         }
      }

      // Did the user set a limit
      if (isset($numRows)) {
         $this->_query .= " LIMIT " . (int) $numRows;
      }

      // Prepare query
      $stmt = $this->_prepareQuery();

      // Bind parameters
      if ($hasTableData) {
         $args = array();
         $args[] = $this->_paramTypeList;
         foreach ($tableData as $prop => $val) {
            $args[] = &$tableData[$prop];
         }
          if($this->_wheres){
              foreach($this->_wheres as $prop => $val) {
                  $args[] = &$this->_wheres[$prop];
              }
          }
          if($this->_where)
              $args[] = &$where_value;
         call_user_func_array(array($stmt, 'bind_param'), $args);
      } else if(count($this->_wheres) > 0) {
          $args = array();
          $args[] = $this->_paramTypeList;
          $args[] = &$where_value;
          if($this->_wheres){
              foreach($this->_wheres as $prop => $val) {
                  $args[] = &$this->_wheres[$prop];
              }
          }
          call_user_func_array(array($stmt, 'bind_param'), $args);
      } else {
         if ($this->_where)
            $stmt->bind_param($this->_paramTypeList, $where_value);
      }

      return $stmt;
   }

   /**
    * This helper method takes care of prepared statements' "bind_result method
    * , when the number of variables to pass is unknown.
    *
    * @param object $stmt Equal to the prepared statement object.
    * @return array The results of the SQL fetch.
    */
   protected function _dynamicBindResults($stmt) 
   {
      $parameters = array();
      $results = array();

      $meta = $stmt->result_metadata();

      while ($field = $meta->fetch_field()) {
         $parameters[] = &$row[$field->name];
      }

      call_user_func_array(array($stmt, 'bind_result'), $parameters);

      while ($stmt->fetch()) {
         $x = array();
         foreach ($row as $key => $val) {
            $x[$key] = $val;
         }
         $results[] = $x;
      }
      return $results;
   }


   /**
   * Method attempts to prepare the SQL query
   * and throws an error if there was a problem.
   */
   protected function _prepareQuery() 
   {
       /**
        * has the user called whereBetween
        */
       if(count($this->_whereBetween) > 0) {
           $column = $this->_whereBetween[0];
           $val1 = $this->_whereBetween[1];
           $val2 = $this->_whereBetween[2];
           $this->_query .= " WHERE ". $column . " >= ". $val1
               ." AND ". $column . " <= ". $val2;
       }

       if(!empty($this->_groupBy))
       {
           $this->_query .= " GROUP BY ". $this->_groupBy;
       }

       if(!empty($this->_orderBy))
       {
           $OKeys = array_keys($this->_orderBy);
           $order_prop = $OKeys[0];
           $order_value = $this->_orderBy[$order_prop];
           $this->_query .= " order by ".$order_prop." ". $order_value;
       }




      if (!$stmt = $this->_mysql->prepare($this->_query)) {
         trigger_error("Problem preparing query", E_USER_ERROR);
      }
      return $stmt;
   }


   public function __destruct() 
   {
		$this->_mysql->close();
   }

}

