<?php
require_once './../loader.php';

$localCfg1 = [
    'dsn' => "mysql:host=localhost;dbname=test;charset=utf8",
    'username' => "root",
    'passwd' => "",
];

$localCfg2 = [
    'dsn' => "mysql:host=localhost;dbname=test;charset=utf8",
    'username' => "root",
    'passwd' => "",
];

$transfer = new \DB\Transfer($localCfg1, $localCfg2);
$transfer->setExistsAction('rewrite');


$transfer->copy([
    'author' => 'author2',
    'news' => [
        'table' => 'news2',
        'fields' => function($ind, $name, $data) {
            if ($name == 'content') {
                $annotateData = $data;
                $annotateData['type'] = 'varchar(250)';
                return [
                    "annotate" => $annotateData,
                    $name => $data
                ];
            }
            return [$name => $data];
        },
        'handler' => function($name, $type, $value) {
            if ($name == 'annotate') {
                return trim(mb_substr($value, 0, 200, 'utf8'));
            }
            if ($name == 'content') {
                return trim($value);
            }
            return $value;
        }
    ],
    'like' => [
        'table' => 'like2',
        'fields' => [
            'whitelist',
            'id' => 'id',
            'newsId' => 'newsId',
        ],
        'types' => [
            'blacklist',
            'varchar'
        ]
    ],
    [
        'sql' => \SQLBuilder\MySQLBuilder::start()
            ->from('news', 'n')
            ->innerJoin('like', ['l.newsId'=>'n.id'], 'l'),
        'table' => 'liked_news',
        /*'columns' => [
        'id' => ['type' => 'int(11)', 'null' => false, 'default' => '', 'primary' => true, 'key' => true, 'extra' => 'auto_increment'],
        ], //*/
    ]
]);
