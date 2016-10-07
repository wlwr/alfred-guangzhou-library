<?php 
require_once('./library.php');
require_once('./workflows.php');
$library = new Library();
$query = urlencode(trim($argv[1]));
$workflows = new Workflows();
$books = $library->search($query);
foreach ($books as $book) {
    $stockTips = $book['stock'] ? "剩余{$book['stock']}本" : '空空如也~';
    $workflows->result($book['id'], 'http://opac.gzlib.gov.cn/opac/book/' . $book['id'], strip_tags($book['title']), $stockTips, 'icon.png');
}
echo $workflows->toxml();
