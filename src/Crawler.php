<?php

use PDO;

class Crawler
{
    private $url;
    private $host;

    private $dbh;

    public function __construct($url)
    {
        $url_components = parse_url($url);

        $this->url  = $url_components['scheme'] . "://" . $url_components['host'];
        $this->host = $url_components['host'];

        if (isset($url_components['port'])) {
            $this->url .= ":" . $url_components['port'];
        }


    }

    protected function createDatabase()
    {
        $database_path = sys_get_temp_dir() . $this->host . '.sqlite';

        $is_new = ! file_exists($database_path);

        $this->dbh = new PDO("sqlite:" . $database_path);
        $this->dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->dbh->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ);

        if ($is_new) {
            $this->dbh->exec("
              CREATE TABLE IF NOT EXISTS links (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                link VARCHAR NOT NULL UNIQUE,
                processed INTEGER NOT NULL)
            ");
        }
    }

    protected function addLink($url)
    {

    }

    protected function parseLinks($url)
    {

    }
}