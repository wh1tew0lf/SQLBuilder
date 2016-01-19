<?php
require_once './../loader.php';

$pdo = \DB\BasePDO::create('mysql:dbname=test;host=localhost', 'root', '1p2h3p4');

$ids = [];
$iSql = \SQLBuilder\MySQLBuilder::start()->insert('author', [
    'name' => ':name',
    'birthday' => ':birthday'
]);
$stmt = $pdo->prepare($iSql);
if ($stmt instanceof PDOStatement) {
    foreach(['Vasay', 'Petya', 'Nikolay', 'Kolya', 'Masha'] as $fio) {
        $stmt->execute([
            ':name' => $fio,
            ':birthday' => date('Y-m-d', time() - rand(1, 500) * 86400)
        ]);
        $ids[] = $pdo->lastInsertId();
    }
}

$newsIds = [];
foreach([
    'first' => 'Sed ut perspiciatis unde omnis iste natus error sit voluptatem accusantium doloremque laudantium, totam rem aperiam, eaque ipsa quae ab illo inventore veritatis et quasi architecto beatae vitae dicta sunt explica',
    'second' => 'But I must explain to you how all this mistaken idea of denouncing pleasure and praising pain was born and I will give you a complete account of the system, and expound the actual teachings of the great explorer of the truth, the master-builder of human happiness. No one rejects, dislikes, or avoids pleasure itself, becau',
    'third' => 'cepturi sint occaecati cupiditate non provident, similique sunt in culpa qui officia deserunt mollitia animi, id est laborum et dolorum fuga. Et harum quid',
    'fourth' => 'o blinded by desire, that they cannot foresee the pain and trouble that are bound to ensue; and equal blame belongs to those who fail in their duty through weakness of will, which is the same as saying through shrinking from toil and pain. These cases are perfectly simple and easy to distinguish. In a',
] as $title => $content) {

    $iSql = \SQLBuilder\MySQLBuilder::start()->insert('news', [
        'authorId' => ':authorId',
        'title' => ':title',
        'content' => ':content',
        'date' => ':date',
    ]);

    $stmt = $pdo->prepare($iSql);
    if ($stmt instanceof PDOStatement) {
        $stmt->bindValue(':authorId', $ids[rand(0, count($ids) - 1)]);
        $stmt->bindValue(':title', $title);
        $stmt->bindValue(':content', $content);
        $stmt->bindValue(':date', date('Y-m-d', time() - rand(1, 500) * 86400));
        $stmt->execute();
        $newsIds[] = $pdo->lastInsertId();
    }
}

foreach($newsIds as $id) {
    $iSql = \SQLBuilder\MySQLBuilder::start()->insert('like', [
        'newsId' => ':newsId',
    ]);

    for($i = 0; $i < rand(1, 5); ++$i) {
        $stmt = $pdo->prepare($iSql);
        if ($stmt instanceof PDOStatement) {
            $stmt->bindValue(':newsId', $id, PDO::PARAM_INT);
            $stmt->execute();
        }
    }
}

$sql = \SQLBuilder\MySQLBuilder::start()
    ->select(['au.id', 'au.name', 'news.title', 'cnt' =>
        \SQLBuilder\MySQLBuilder::start()
            ->select(new \SQLBuilder\BaseExpression('count(*)'))
            ->from('like', 'l')
            ->where(['l.newsId' => 'news.id'])
    ])
    ->from('author', 'au')
    ->innerJoin('news', ['news.authorId' => 'au.id'])
    ->where(['like', 'au.name', ':test'])
    ->getSQL();

$rows = $pdo->execute($sql, [':test' => 'Vasay'])->fetchAll(PDO::FETCH_ASSOC);
foreach($rows as $row) {
    foreach($row as $value) {
        echo "|$value\t";
    }
    echo "\n";
}