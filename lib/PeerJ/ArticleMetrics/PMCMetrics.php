<?php

namespace PeerJ\ArticleMetrics;

/**
 * Fetch metrics for viewers of an article, from PubMed Central
 */
class PMCMetrics extends Metrics
{
    /** @{inheritdoc} */
    protected $name = 'pmc';

    /** @{inheritdoc} */
    protected $suffix = 'xml';

    /** @{inheritdoc} */
    public function fetch($article)
    {
        throw new \Exception('Use fetchAll instead');
    }

    /**
     * Fetch metrics for all articles
     *
     * @throws \Exception
     */
    public function fetchAll()
    {
        $date = new \DateTime('-1 MONTH'); // last month
        $earliest = new \DateTime($this->config['earliest']);

        $i = 0;

        do {
            $file = $this->dir . sprintf('/original/%d.xml', $i++);

            $params = array(
                'user' => $this->config['user'],
                'password' => $this->config['pass'],
                'jrid' => $this->config['jrid'],
                'year' => $date->format('Y'),
                'month' => $date->format('n'), // single-digit month
            );

            $this->get('http://www.pubmedcentral.nih.gov/utils/publisher/pmcstat/pmcstat.cgi', $params, $file);

            $date->modify('-1 MONTH');
        } while ($date > $earliest);
    }

    /** @{inheritdoc} */
    public function parse()
    {
        $items = array();

        // sum counts for each month
        foreach ($this->files() as $file) {
            $doc = new \DOMDocument;
            $doc->load($file);

            $xpath = new \DOMXPath($doc);
            $nodes = $xpath->query('articles/article');

            foreach ($nodes as $node) {
                $doi = $xpath->evaluate('string(meta-data/@doi)', $node);
                $count = $xpath->evaluate('number(usage/@full-text)', $node);

                if (!isset($items[$doi])) {
                    $items[$doi] = 0;
                }

                $items[$doi] += $count;
            }
        }

        // output total counts per article
        $output = $this->getOutputFile();
        fputcsv($output, array('id', 'count'));

        foreach ($items as $doi => $count) {
            $data = array(
                'id' => $this->id_from_doi($doi),
                'count' => $count,
            );

            fputcsv($output, $data);
        }

        fclose($output);
    }


    /**
     * TODO: ask for the article ID in the response
     *
     * @param string $doi
     *
     * @return string
     */
    protected function id_from_doi($doi)
    {
        preg_match('/(\d+)$/', $doi, $matches);

        if (!$matches) {
            exit("No ID in DOI: $doi\n");
        }

        return $matches[1];
    }
}
