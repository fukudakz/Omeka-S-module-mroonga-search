<?php
namespace MroongaSearch;

use Omeka\Api\Manager as ApiManager;
use Doctrine\DBAL\Connection;

class MroongaSearchService
{
    protected $api;
    protected $connection;

    public function __construct(ApiManager $api, Connection $connection)
    {
        $this->api = $api;
        $this->connection = $connection;
    }

    /**
     * ユーザ入力を BOOLEAN MODE 用に変換する。
     * - 英数字のみの語（ATLUS 等）: 語全体を +語（AND 必須）
     * - 日本語等: 2-gram ごとに +
     * - 日本語と英数字の混在（ニンテンドーDS 等）: 英数字部分は +語、日本語部分は 2-gram（境界で分割）
     * Omeka コアの fulltext_search（api.search.query）でも利用する。
     */
    public function buildBooleanBigramQuery(string $query): string
    {
        $query = str_replace('　', ' ', $query);
        $query = preg_replace('/\s+/', ' ', trim($query));

        if ($query === '') {
            return '';
        }

        $terms = explode(' ', $query);
        $parts = [];

        foreach ($terms as $term) {
            $term = trim($term);
            if ($term === '') {
                continue;
            }

            if ($this->isAsciiWordToken($term)) {
                $parts[] = $this->formatBooleanRequiredToken($term);
                error_log("MroongaSearch: Term '{$term}' -> ASCII word (no bigram)");
                continue;
            }

            foreach ($this->expandMixedTermToBooleanPieces($term) as $piece) {
                $parts[] = $piece;
            }
        }

        $result = implode(' ', $parts);
        error_log('MroongaSearch: Final boolean query: ' . $result);

        return $result;
    }

    /**
     * 混在語を英数字連続とそれ以外に分け、それぞれ BOOLEAN フラグメントに展開する。
     *
     * @return string[] 例: ニンテンドーDS → +ニン +ンテ … +DS
     */
    private function expandMixedTermToBooleanPieces(string $term): array
    {
        $out = [];
        foreach ($this->segmentMixedTerm($term) as $segment) {
            $kind = $segment[0];
            $text = $segment[1];
            if ($text === '') {
                continue;
            }
            if ($kind === 'ascii') {
                $out[] = $this->formatBooleanRequiredToken($text);
                error_log("MroongaSearch: segment ASCII '{$text}'");
                continue;
            }
            $bigrams = $this->generateBigrams($text);
            error_log('MroongaSearch: segment CJK bigrams: ' . json_encode($bigrams, JSON_UNESCAPED_UNICODE));
            foreach ($bigrams as $bigram) {
                if ($bigram !== '') {
                    $out[] = $this->formatBooleanRequiredToken($bigram);
                }
            }
        }

        return $out;
    }

    /**
     * 1 語の中で、ASCII 英数字の連続とそれ以外（日本語・記号など）に分割する。
     *
     * @return list<array{0: 'ascii'|'cjk', 1: string}>
     */
    private function segmentMixedTerm(string $term): array
    {
        $segments = [];
        $len = mb_strlen($term);
        $i = 0;
        while ($i < $len) {
            $ch = mb_substr($term, $i, 1);
            if ($this->isAsciiWordLeadingChar($ch)) {
                $start = $i;
                $i++;
                while ($i < $len) {
                    $c = mb_substr($term, $i, 1);
                    if ($this->isAsciiWordContinuationChar($c)) {
                        $i++;
                    } else {
                        break;
                    }
                }
                $segments[] = ['ascii', mb_substr($term, $start, $i - $start)];
                continue;
            }
            $start = $i;
            $i++;
            while ($i < $len) {
                $c = mb_substr($term, $i, 1);
                if ($this->isAsciiWordLeadingChar($c)) {
                    break;
                }
                $i++;
            }
            $segments[] = ['cjk', mb_substr($term, $start, $i - $start)];
        }

        return $segments;
    }

    private function isAsciiWordLeadingChar(string $ch): bool
    {
        return strlen($ch) === 1 && ctype_alnum($ch);
    }

    private function isAsciiWordContinuationChar(string $ch): bool
    {
        if (strlen($ch) !== 1) {
            return false;
        }

        return ctype_alnum($ch) || in_array($ch, ['-', '_', '.'], true);
    }

    /**
     * 英数字・ハイフン・アンダースコア・ドットのみの語（Mroonga の単語索引向け）。
     * 日本語・全角・記号混じりは false（2-gram 側へ）。
     */
    private function isAsciiWordToken(string $term): bool
    {
        if ($term === '') {
            return false;
        }
        if (preg_match('/[^\x20-\x7E]/', $term)) {
            return false;
        }

        return (bool) preg_match('/^[A-Za-z0-9][A-Za-z0-9\-_.]*$/', $term);
    }

    /**
     * BOOLEAN MODE で必須語として安全に渡す（+term または +"phrase"）。
     * 2-gram 側でも使う。~ + - などは演算子なので、含む語は引用する。
     * パラメータバインドで渡すため、SQL リテラル用の二重エスケープはしない。
     */
    private function formatBooleanRequiredToken(string $term): string
    {
        // mroonga_escape() の既定に加え @。OR は演算子（大文字）。
        $needsQuotes = strcasecmp($term, 'OR') === 0
            || preg_match('/[+\-<>~*()"\\\\:@]/', $term);

        if ($needsQuotes) {
            $escaped = str_replace(['\\', '"'], ['\\\\', '\\"'], $term);

            return '+"' . $escaped . '"';
        }

        return '+' . $term;
    }

    public function enhancedSearch($query, $options = [])
    {
        $booleanQuery = $this->buildBooleanBigramQuery((string) $query);

        error_log('MroongaSearch: Original query: ' . $query);
        error_log('MroongaSearch: Boolean query: ' . $booleanQuery);

        if ($booleanQuery === '') {
            return [];
        }

        $results = $this->basicFulltextSearch($booleanQuery);

        error_log('MroongaSearch: Results count: ' . count($results));

        return $this->adjustRelevanceScores($results, $query);
    }

    /**
     * 例: "ポケモン" -> ["ポケ", "ケモ", "モン"]
     */
    private function generateBigrams($text)
    {
        $length = mb_strlen($text);
        if ($length < 2) {
            return [$text];
        }

        $bigrams = [];
        for ($i = 0; $i < $length - 1; $i++) {
            $bigram = mb_substr($text, $i, 2);
            if ($bigram !== '') {
                $bigrams[] = $bigram;
            }
        }

        return $bigrams;
    }

    private function basicFulltextSearch($query)
    {
        $sql = 'SELECT *,
            MATCH(title, text) AGAINST(:query IN BOOLEAN MODE) as relevance
        FROM fulltext_search
        WHERE MATCH(title, text) AGAINST(:query IN BOOLEAN MODE)
        ORDER BY relevance DESC';

        $stmt = $this->connection->prepare($sql);
        $stmt->execute(['query' => $query]);

        return $stmt->fetchAll();
    }

    private function adjustRelevanceScores($results, $query)
    {
        foreach ($results as &$result) {
            $adjustedScore = $result['relevance'];
            $title = $result['title'];
            $queryLength = mb_strlen($query);
            $titleLength = mb_strlen($title);

            if ($titleLength <= $queryLength * 2) {
                $adjustedScore *= 1.2;
            }

            $result['adjusted_relevance'] = $adjustedScore;
        }

        usort($results, function ($a, $b) {
            return $b['adjusted_relevance'] <=> $a['adjusted_relevance'];
        });

        return $results;
    }
}
