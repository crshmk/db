<?php


/*
TODO:
single quotes occur around JOIN condition; edit condition() to account
add logic for passing array of params; old class handles all in one fn
check functionality for passing ? or string value
put quotes around condition args
write UPDATE fn
write a constructor
namespace this in folders
create traits for the mixin-like functions
inject into a DB class
comment everything
readme
check JOIN statements
handle stings like the slice() fn in the old DB class
add SELECT DISTINCT statement
complete select fn with all options e.g. EXISTS
error check params

*/

/**
 *  Create sql query string for CRUD operations
 *
 *  Exposes the following methods:
 *    select
 *    insert
 *    update
 *    qDelete
 *    c(), r(), u(), d() aliases for the above
 *    all
 *
 *
 *  Example queries:
 *    all('products')
 *      'SELECT * FROM products'
 *    select(['id', 'name', 'price'], 'products', ['price', '>', 1000])
 *      'SELECT id, name, price from products where price > 1000'
 *    insert('users', ['name' => 'mersault', 'email' => 'ennui@gmail.com'])
 *      'INSERT INTO users (name, email) VALUES (?, ?)'
 *
 *  more examples in app.php
 *
 *
 *
 *  COUNT(), AVG(), AND SUM() functions should be called as part of the action string
 *    e.g. $action = 'select AVG(price)';
 *  Likewise with SELECT ... INTO
 */

class SQL {

  public $query;

  public function __construct() {

  }

  /**
   * Remove final ', ' or ',' from a string
   *
   * @param string
   */
  private function chopComma($s) {
  return rtrim(trim($s), ',');
  }

  /**
   * Create a string of comma separated values
   *
   * @param array
   * @return string
   */
  private function csv($a) {
  return implode(', ', $a);
  }

  /**
   * Create comma separated list inside parens, i.e.
   *  (price, quantity) or (?, ?)
   *
   * @param array $vals
   * @param boolean $secure : convert values to '?' for PDO preparation
   */
  private function parens($vals, $secure=false) {
    $vals = $secure ? array_fill(0, count($vals), '?') : $vals;
    return '(' . $this->csv($vals) . ')';
  }

  /**
   * Add JOIN clause
   */
  private function join($j) {
  $where = false;
  $sql = ' ' . $j->type . ' JOIN ';
  $sql .= $j->table . ' ON';
  $sql .= $this->condition($j->condition, $where);
      return $sql;
  }

  /**
   * Add condition clause
   *
   * @param array $w :
   *   pass multiple conditions with nested arrays
   * @param boolean $isWhere : condition is a where clause
   */
  private function condition($w, $isWhere = true) {

  if (is_array($w[0])) {
      // only state WHERE in the first condition
  $where = false;
  $sql = '';
  foreach($w as $op => $condition) {
  $sql .= $sql === '' ? $this->condition($condition) :
    ' ' . $op . $this->condition($condition, $where);
  }
    return $sql;
  }

  // operator is passed in position [1]
  $w[1] = trim($w[1]);

  $sql = $isWhere ? " WHERE {$w[0]} " : " {$w[0]} " ;
  switch($w[1]) {
  case 'IN':
  case 'NOT IN':
    $sql .= "{$w[1]} " . $this->parens($w[2]);
    break;
    case 'BETWEEN':
  case 'NOT BETWEEN':
  $sql .= "{$w[1]} '{$w[2]}' AND '{$w[3]}' ";
  break;
  case 'IS NULL':
  case 'IS NOT NULL':
    $sql .= $w[1];
    default:
    $sql .= "{$w[1]} '{$w[2]}'";
  }
  return $sql;
  }

  /**
   * Add WHERE EXISTS clause
   */
  private function sqlExists($query) {
    return ' WHERE EXISTS (' . $this->select($query) . ')';
  }

  /**
   *  Handle configuration passed as object
   */
  private function sqlFromObject($q) {
    $a = is_array($q->action) ? 'SELECT ' . $this->csv($q->action) : $q->action;
    $sql = $a . ' FROM ' . $q->table;
    $sql .= isset($q->join) ? $this->join($q->join) : '';
    $sql .= isset($q->where) ? $this->condition($q->where) : '';
    $sql .= isset($q->groupBy) ? $this->concat('GROUP BY', $q->groupBy) : '';
    $sql .= isset($q->having) ? $this->concat('HAVING', $q->groupBy) : '';
    $sql .= isset($q->orderBy) ? $this->sqlOrderBy($q->orderBy) : '';
    return $sql;
  }

  /**
   * Add ORDER BY clause
   */
  private function sqlOrderBy($orderBy) {
    $sql = ' ORDER BY';
    foreach($orderBy as $ob) {
    $sql .= (count($ob) > 1) ? " {$ob[0]} DESC," : " {$ob} ASC,";
  }
  return $this->chopComma($sql);
  }

  /**
   *  Add GROUP BY or HAVING clause
   *
   * @param string $command : 'GROUP BY' or 'HAVING'
   * @param string $condition
   */
  private function concat($command, $condition) {
    return ' ' . $command . ' ' . $condition;
  }

  /**
   *  Entry point of class
   *  @param $action :
   *    string, e.g. 'SELECT id, name'
   *    array, e.g. ['products.id', 'products.name', 'users.id']
   *    object, e.g. { action: 'SELECT *', where: ['id', '>', 42], orderBy: 'country'}
   *
   */
  public function select($action, $table = '', $where = [], $orderBy = []) {
  if (is_object($action)) {
    return $this->sqlFromObject($action);
  }

  // pass multiple selects as an array
  if (is_array($action)) {
    $select = 'SELECT ' . $this->csv($action);
    return $this->select($select, $table, $where, $orderBy);
  }

  $sql = "{$action} FROM {$table}";
  $sql .= count($where) ? $this->condition($where) : '';
  $sql .= count($orderBy) ? $this->sqlOrderBy($orderBy) : '';

  return $sql;
  }

  /**
   * Create INSERT statement
   *
   * @param string $table
   * @param associativearray $fields
   *   e.g. ['product' => 'chair', 'price' => 42]
   */
  public function insert($table, $fields) {
    $columns = array_keys($fields);
    return "INSERT INTO {$table} " .
      $this->parens($columns) .
      " VALUES " .
      $this->parens($fields, $secure=true);
  }


  /**
   * Create DELETE statement
   */
  public function qDelete($table, $where=[]) {
    $sql = 'DELETE FROM ' . $table . $this->condition($where);
    return $sql;
  }

  /**
   * Create SELECT * FROM $table statement
   */
  public function all($table) {
  return $this->select('SELECT *', $table);
  }

  /**
   * Create UNION or UNION ALL statement
   * @param object $q1 query object
   * @param object $q2 query object
   * @param boolean $all : Create UNION ALL statement
   */
  public function union($q1, $q2, $all=false) {
    $operator = $all ? ' UNION ALL ' : ' UNION ';
    return $this->select($q1) . $operator . $this->select($q2);
  }

  /**
   * CRUD-named convenience methods
   */
  public function c($table, $fields) {
    return $this->insert($table, $fields);
  }
  public function r($action, $table = '', $where = [], $orderBy = []) {
    return $this->select($action, $table, $where, $orderBy);
  }
  public function u() {

  }
  public function d($table, $where=[]) {
    return $this->qDelete($table, $where);
  }

 }
