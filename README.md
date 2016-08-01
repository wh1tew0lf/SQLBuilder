# SQLBuilder
Classes for generate SQL queries (select/insert/update/delete) on different platforms (MySQL, MS SQL)

### For run tests:
`./vendor/bin/phpunit`

### For create documentation:
Don't forget install graphViz
`sudo apt-get install graphviz`
`./vendor/bin/phpdoc -p -d ./src -t ./docs/`

### For get metrics and statistics:
1. Shows some metrics
`./vendor/bin/phpmetrics --report-html=./docs/metrics.html ./src/`
2. Shows table of errors, code style problems and etc
`./vendor/bin/phpmd ./src/ html codesize,unusedcode,naming --reportfile ./docs/project_size.html`
3. Shows duplicated code
`./vendor/bin/phpcpd --progress ./src/ > ./docs/phpcpd_stat.txt`
4. Shows summary for code
`./vendor/bin/phploc ./src/ > ./docs/phploc_stat.txt`

###To Do section
* ~~Check MSSQLPDO class and try transfer with it.~~

###Can be done, but not necessary
* ~~Table transfer class from MySQL to MSSQL and from MS to My~~
* Transfer from CSV and from MS to MS or My to My (Do I need to do it?)
* Make tests    (0.5%)
* Make examples (2%)

###Purposes
1. Provide tools for automatic copy/move tables from one db/engine to another if there are no access to mysql/mysqldump
2. If you add CSVSource/CSVDestination (or other DB support) then it can be used for import/export CSV and etc.