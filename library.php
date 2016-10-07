<?php

class Library
{

    protected $searchApi = 'http://opac.gzlib.gov.cn/opac/search?rows=10&scWay=dim&searchWay=title&f_curlibcode=GT&q=%s';

    protected $queryStockApi = 'http://opac.gzlib.gov.cn/opac/book/holdingpreview/%s';

    public function search($keyword)
    {
        try {
            $searchApi = sprintf($this->searchApi, $keyword);
            $result = $this->makeRequest($searchApi);
            if ($result['code'] == 200) {
                preg_match("#<table\sclass=\"resultTable\">([\s\S]*?)</table>#", $result['result'], $match);
                if (!$match || !isset($match[1])) {
                    throw new Exception("Search Error", 1);
                }
                $html = $match[1];
                $result = [];
                $titles = $this->getTitles($html);
                $ids = $this->getIds($html);
                foreach ($ids as $index => $id) {
                    $stock = $this->queryStock($id);
                    $result[] = [
                        'id' => $id,
                        'title' => $titles[$index],
                        'stock' => $stock,
                    ];
                }
                return $result;
            } else {
                throw new Exception('REQUEST ERROR: ' . $result['code'], 0);
            }

        } catch (Exception $e) {

        }

    }

    public function getTitles($html)
    {
        preg_match_all("#<span\sclass=\"bookmetaTitle\">([\s\S]*?)</span>#", $html, $matches);
        $titles = [];
        foreach ($matches[1] as $match) {
            $titles[] = trim(strip_tags($match));
        }
        return $titles;
    }

    public function getIds($html)
    {
        preg_match_all("#id=\"title_(\d*)\"#", $html, $matches);
        $ids = [];
        foreach ($matches[1] as $match) {
            $ids[] = intval($match);
        }
        return $ids;
    }

    public function queryStock($id)
    {
        $queryStockApi = sprintf($this->queryStockApi, $id);
        $result = $this->makeRequest($queryStockApi);
        if ($result['code'] == 200) {
            $result = $result['result'];
            $xml = simplexml_load_string($result);
            foreach ($xml->record as $record) {
                if ((string) $record->curlibName == '广州图书馆') {
                    return (int) $record->copycount;
                }
            }
            return 0;
        }
        return '未知';
    }

    public function makeRequest($url, $argument = array(), $ttl = 5, $method = "GET")
    {
        if (!$url) {
            throw new Exception('$url不能为空');
        }

        if (substr($url, 0, 7) != 'http://' && substr($url, 0, 8) != 'https://') {
            return array('result' => null, 'code' => '400');
        }
        if ($method == 'GET' && count($argument) > 0) {
            $url .= "?" . (http_build_query($argument));
        }
        $header = array(
            'Accept-Language: zh-cn',
            'Connection: Keep-Alive',
            'Cache-Control: no-cache',
        );

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        if ($method == 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            $argument = http_build_query($argument);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $argument);
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $ttl);
        curl_setopt($ch, CURLOPT_USERAGENT, 'ALFRED API REQUEST(CURL)');
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        $return = array();
        $return['result'] = curl_exec($ch);
        $return['code'] = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        unset($ch);

        return $return;
    }

}
