<?php
// TODO namespace this
require_once('./SQL.php');

// this will later be injected into a DB class
$sql = new SQL();


/*
  select all
*/
$sql->all('products');
 // -> 'SELECT * FROM products'

/*
  select and filter
*/
$sql->select('SELECT MAX(age)', 'customers', ['id', 'IN', [42,56,67,78]]);
// -> 'SELECT MAX(age) FROM customers WHERE id IN (42, 56, 67, 78)'

$cols = ['id', 'name', 'email'];
$where = [
           [ 'id', 'IN', [43,56,67,78] ],
  'AND' => [ 'country', '=', 'Vietnam' ],
  'OR'  => [ 'status', '>', 3 ]
];
$sql->r($cols, 'users', $where);
// -> SELECT id, name, email FROM users
//    WHERE id IN (43, 56, 67, 78)
//    AND country = 'Vietnam'
//    OR status > '3'

/*
  select, filter, and order
  select aliased as r
*/
// TODO true value for DESC looks a little magic; find a more readable param
$sql->r(
  'SELECT *',
  'products',
  ['id', 'BETWEEN', 42, 56],
  [ ['id', true], 'name', 'collection' ]
);
// -> 'SELECT * FROM products
//     WHERE id BETWEEN 42 AND 56
//     ORDER BY id DESC, name ASC, collection ASC'

/*
  complex queries need to pass an object
*/
$join = new stdClass();
$join->type = 'LEFT';
$join->table = 'images';
$join->condition = ['products.id', '=', 'images.product'];

$q = new stdClass();
$q->action = ['products.id', 'products.name', 'images.filename'];
$q->table = 'products';
$q->where = ['products.id', 'BETWEEN', 42, 142];
$q->orderBy = [ ['products.id', true], 'products.name', 'products.collection' ];
$q->join = $join;

$d = $sql->r($q);
var_dump($d);
//   'SELECT * FROM products
//    LEFT JOIN customers
//    ON customers.id = product.customer
//    WHERE products.id BETWEEN 42 AND 142
//    ORDER BY products.id DESC, products.name ASC, products.collection ASC'


/*
  insert (aliased as c)
    includes PDO preparation
*/

$fields = [
  'name'       => 'Nice Chair',
  'collection' => 'Vintage',
  'category'   => 'Dining',
  'price'      => 42
];
$sql->c('products', $fields);
// or $sql->insert()
// -> INSERT INTO products (name, collection, category, price) VALUES (?, ?, ?, ?)

/*
  delete (aliased as d)
*/
$where = [
           ['collection', '=', 'Vintage'],
  'AND' => ['id',         '<',  900]
];
$sql->d('products', $where);
// or $sql->delete()
// -> DELETE FROM products WHERE collection = 'Vintage' AND id < '900'
