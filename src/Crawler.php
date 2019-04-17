<?php

use GuzzleHttp\Client;

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

        if (isset($url_components['port']) && $url_components['port'] != 80) {
            $this->url .= ":" . $url_components['port'];
        }

        $this->createDatabase();
    }

    public function start()
    {
        $start_link = $this->url . '/';

        if (!$this->linkExists($start_link)) {
            $this->addLink($start_link);
        }

        while ($link_object = $this->getNextNotProcessedLink()) {

            $links = $this->parseLinks($link_object->link);

            foreach ($links as $link) {
                if (!$this->linkExists($link)) {
                    echo "$link\n";
                    $this->addLink($link);
                }
            }

            $this->markLinkAsProcessed($link_object->id);
        }
    }

    protected function createDatabase()
    {
        $database_path = sys_get_temp_dir() . '/' . $this->host . '.sqlite';

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

    protected function addLink($link)
    {
        $sth = $this->dbh->prepare("INSERT INTO links (link, processed) VALUES (:link, 0)");
        $sth->execute([
            ':link' => $link,
        ]);
    }

    protected function linkExists($link)
    {
        $stmt = $this->dbh->prepare("SELECT COUNT(*) FROM links WHERE link = :link");
        $stmt->execute([':link' => $link]);
        return $stmt->fetchColumn() ? true : false;
    }

    protected function markLinkAsProcessed($id)
    {
        $stmt = $this->dbh->prepare("UPDATE links SET processed = 1 WHERE id = :id");
        $stmt->execute([
            ':id' => $id,
        ]);
    }

    protected function getNextNotProcessedLink()
    {
        $sth = $this->dbh->query("SELECT * FROM links WHERE processed = 0 LIMIT 1");
        if ($link = $sth->fetch()) {
            return $link;
        }

        return false;
    }

    protected function parseLinks($link)
    {
        $parsed_links = [];

        $html_page = $this->getHtmlPage($link);

        $dom = new DOMDocument;

        @$dom->loadHTML($html_page);

        $links = $dom->getElementsByTagName('a');

        foreach ($links as $link) {

            $href = $link->getAttribute('href');

            if (!$href || substr($href, 0, 1) == "#") {
                continue;
            }

            if (substr($href, 0, 4) != 'http') {
                if (substr($href, 0, 1) == '/') {
                    $href = $this->url . $href;
                } else {
                    throw new Exception('Relative paths not supported.');
                }
            }

            if (!$this->linkBelongsToTheHost($href)) {
                continue;
            }

            $parsed_links[] = $href;
        }

        return $parsed_links;
    }

    protected function linkBelongsToTheHost($link)
    {
        $link_components = parse_url($link);
        return $this->host == $link_components['host'];
    }

    protected function getHtmlPage($link)
    {
        $client = new Client([
            'timeout' => 5,
        ]);

        $response = $client->get($link);

        if ($response->getStatusCode() != 200) {
            throw new Exception("Server answer code isn't 200.");
        }

        return (string) $response->getBody();
    }
}