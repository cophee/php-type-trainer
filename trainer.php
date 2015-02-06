<?php

namespace mpyw\EnglishTypeTrainer;

final class DPMatcher {
    
    const INSERTED_ERROR = 'I';
    const DELETED_ERROR = 'D';
    const SUBSUTITUTED_ERROR = 'S';
    const GOOD_MSG = '[ GOOD ]';
    const BAD_MSG = '[ BAD ]';
    
    private function __construct() { }
    
    public static function match($expected, $actual) {
        $ysize = strlen($expected);
        $xsize = strlen($actual); 
        $score = array(array(0));
        $vector = array(array(null));
        for ($y = 1; $y <= $ysize; ++$y) {
            $score[0][$y] = $y;
            $vector[0][$y] = array('x' => 0, 'y' => $y - 1);
        }
        for ($x = 1; $x <= $xsize; ++$x) {
            $score[$x][0] = $x;
            $vector[$x][0] = array('x' => $x - 1, 'y' => $y);
        }
        for ($x = 1; $x <= $xsize; ++$x) {
            for ($y = 1; $y <= $ysize; ++$y) {
                $min = min($score[$x - 1][$y], $score[$x][$y - 1], $score[$x - 1][$y - 1]);
                if ($min === $score[$x - 1][$y]) {
                    $mx = $x - 1;
                    $my = $y;
                    $rate = 1;
                } elseif ($min === $score[$x][$y - 1]) {
                    $mx = $x;
                    $my = $y - 1;
                    $rate = 1;
                } else {
                    $mx = $x - 1;
                    $my = $y - 1;
                    $rate = 2;
                }
                $score[$x][$y] = $score[$mx][$my] + (bool)strcasecmp($expected[$y - 1], $actual[$x - 1]) * $rate;
                $vector[$x][$y] = array('x' => $mx, 'y' => $my);
            }
        }
        $result = array(' ', ' ', $xsize ? self::GOOD_MSG : self::BAD_MSG);
        for ($x = $xsize, $y = $ysize; $v = $vector[$x][$y]; $x = $v['x'], $y = $v['y']) {
            if ($x !== $v['x'] && $y === $v['y']) {
                $result[0][$x - 1] = '~';
                $result[1][$x - 1] = self::INSERTED_ERROR;
                $result[2] = self::BAD_MSG;
            } elseif ($x === $v['x'] && $y !== $v['y']) {
                $result[1][$x - 1] = self::DELETED_ERROR;
                $result[2] = self::BAD_MSG;
            } elseif ($x !== $v['x'] && $y !== $v['y'] && $score[$x][$y] !== $score[$v['x']][$v['y']]) {
                $result[0][$x - 1] = '~';
                $result[1][$x - 1] = self::SUBSUTITUTED_ERROR;
                $result[2] = self::BAD_MSG;
            }
        }
        return $result;
    }
    
}

final class DB {
    
    private static $maxStoredSentences = 1000;
    private static $maxStoredRankings = 5;
    
    private $pdo;
    private $stmt;
    
    private function prepare($sql) {
        if (!isset($this->stmt[$sql])) {
            $this->stmt[$sql] = $this->pdo->prepare($sql);
        }
        return $this->stmt[$sql];
    }
    
    public function __construct($filename) {
        $this->pdo = new \PDO("sqlite:$filename");
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
        $this->pdo->exec('
            CREATE TABLE IF NOT EXISTS
            ranking(
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                score INTEGER NOT NULL,
                acc INTEGER NOT NULL,
                date TEXT NOT NULL
            )
        ');
        $this->pdo->exec('
            CREATE TABLE IF NOT EXISTS
            sentence(
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                text TEXT UNIQUE NOT NULL,
                date TEXT NOT NULL
            )
        ');
    }
    
    public function insertSentences(array $texts, $date) {
        $this->pdo->beginTransaction();
        try {
            foreach ($texts as $text) {
                $stmt = $this->prepare('REPLACE INTO sentence(text, date) VALUES(:text, :date)');
                $stmt->bindValue(':text', $text);
                $stmt->bindValue(':date', $date);
                $stmt->execute();
            }
            $stmt = $this->prepare('
                DELETE FROM ranking WHERE id NOT IN (
                    SELECT id FROM ranking ORDER BY date DESC LIMIT :limit
                )
            ');
            $stmt->bindValue(':limit', self::$maxStoredSentences);
            $stmt->execute();
            $this->pdo->commit();
        } catch (\PDOException $e) {
            $this->pdo->rollBack();
            throw $e;
        }
        return $stmt->rowCount();
    }
    
    public function insertScore($score, $acc, $date) {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->prepare('
                INSERT INTO ranking(score, acc, date)
                VALUES (:score, :acc, :date)
            ');
            $stmt->bindValue(':score', $score);
            $stmt->bindValue(':acc', $acc, \PDO::PARAM_INT);
            $stmt->bindValue(':date', $date);
            $stmt->execute();
            $id = $this->pdo->lastInsertId();
            $stmt = $this->prepare('
                DELETE FROM ranking WHERE id NOT IN (
                    SELECT id FROM ranking ORDER BY score DESC, date ASC LIMIT :limit
                )
            ');
            $stmt->bindValue(':limit', self::$maxStoredRankings);
            $this->pdo->commit();
            return $id;
        } catch (\PDOException $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
    
    public function getRandomSentences($limit) {
        $stmt = $this->prepare('SELECT text FROM sentence ORDER BY RANDOM() LIMIT :limit');
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_COLUMN, 0);
    }
    
    public function getRankings() {
        return $this->pdo->query('
            SELECT id, score, acc, date FROM ranking
            ORDER BY score DESC, date ASC
        ')->fetchAll();
    }
    
}

final class Out {
    
    private function __construct() { }
    
    public static function write($str = '') {
        echo "$str";
    }
    
    public static function writeln($str = '') {
        echo "$str\n";
    }
    
    public static function errorln($str = '') {
        fprintf(STDERR, "%s\n", $str);
    }
    
    public static function error($str = '') {
        fprintf(STDERR, "%s", $str);
    }
    
    public static function prompt($msg = '') {
        if ($msg !== '') {
            self::write("$msg: ");
        }
        return trim(fgets(STDIN));
    }
    
    public static function promptYN($msg = '') {
        while (true) {
            $answer = strtoupper(self::prompt("$msg [Y/N]"));
            if ($answer === 'Y') {
                return true;
            } elseif ($answer === 'N') {
                return false;
            }
        }
    }
    
    public static function promptNumber($msg = '', $min, $max) {
        while (true) {
            $answer = filter_var(self::prompt($msg), FILTER_VALIDATE_INT, array(
                'options' => array(
                    'min_range' => $min,
                    'max_range' => $max,
                ),
            ));
            if ($answer !== false) {
                return $answer;
            }
        }
    }
    
}

final class Application {
    
    private static $sqliteFilename = 'EnglishTypeTrainer.db';
    private static $wikipediaUrl = 'http://simple.wikipedia.org/wiki/Special:Randompage';
    private static $maxRound = 10;
    private static $timeZone = 'Asia/Tokyo';
    
    public static function run() {
        if (PHP_SAPI !== 'cli') {
            header('Content-Type: text/plain; charset=utf-8', true, 400);
            Out::errorln('This software requries PHP-CLI environment.');
            exit(1);
        }
        $opts = getopt('', array('test::'));
        if (isset($opts['test'])) {
            $expected = Out::Prompt('[EXPECTED]');
            $actual = Out::Prompt('[ACTUAL]  ');
            $result = DPMatcher::match($expected, $actual);
            foreach ($result as $line) {
                Out::writeln("            $line");
            }
            exit(0);
        }
        Out::writeln('Hello, this is the English Type Trainer written in PHP.');
        Out::writeln();
        $path = getcwd() . DIRECTORY_SEPARATOR . self::$sqliteFilename;
        if (!is_file($path) && !Out::promptYN("Can I create {$path} ?")) {
            exit(0);
        }
        $db = new DB($path);
        $menu = array(
            1 => 'Start training',
            2 => 'View score ranking',
            3 => 'Create or refresh database',
            0 => 'Quit',
        );
        try {
            while (true) {
                Out::writeln('[ Menu ]');
                foreach ($menu as $i => $text) {
                    Out::writeln("$i. $text");
                }
                $no = Out::prompt('Select by number');
                if (isset($menu[$no])) {
                    $callback = array(__CLASS__, str_replace(' ', '', $menu[$no]));
                    $callback($db);
                }
                Out::writeln();
            }
        } catch (\Exception $e) {
            Out::errorln($e->getMessage());
            exit(1);
        }
    }
    
    private static function startTraining(DB $db) {
        $texts = $db->getRandomSentences(self::$maxRound);
        if ($texts < self::$maxRound) {
            Out::errorln('There are not enough sentences in the database.');
            return;
        }
        if (!Out::promptYN('Ready to start?')) {
            return;
        }
        Out::writeln('');
        $max = count($texts);
        $typed = 0;
        $whole = 0;
        $error = 0;
        for ($i = 5; $i >= 0; --$i) {
            Out::writeln(str_repeat('.', $i));
            sleep(1);
        }
        $start = microtime(true);
        foreach ($texts as $i => $expected) {
            $caption = sprintf('[%02d/%2d]', $i + 1, $max);
            Out::writeln("$caption: $expected");
            $actual = Out::prompt('[INPUT]');
            $result = DPMatcher::match($expected, $actual);
            foreach ($result as $line) {
                Out::writeln("        $line");
            }
            Out::writeln();
            $typed += strlen($actual);
            $whole += strlen($expected);
            $error += count(preg_split('/ ++/', $result[1], -1, PREG_SPLIT_NO_EMPTY));
        }
        $end = microtime(true);
        sleep(1);
        Out::writeln('[ FINISH ]');
        $score = round(60 * ($typed - 3 * $error) / ($end - $start));
        $acc =  round(100 * (1 - $error / max($typed, $whole)));
        Out::writeln("Score: $score");
        Out::writeln("Accuracy: $acc");
        $dt = date_create('now', timezone_open(self::$timeZone));
        $id = $db->insertScore($score, $acc, $dt->format('Y-m-d H:i:s'));
        foreach ($db->getRankings() as $i => $row) {
            if ($row['id'] == $id) {
                Out::writeln('New record! Ranked as #' . ($i + 1));
                break;
            }
        }
        Out::writeln();
    }
    
    private static function viewScoreRanking(DB $db) {
        Out::writeln(' # | Score | Accuracy(%) | DateTime');
        foreach ($db->getRankings() as $i => $row) {
            Out::writeln(sprintf(
                '% 2d | % 5d | % 11d | %8s',
                $i + 1, $row['score'], $row['acc'], $row['date']
            ));
        }
        Out::writeln();
    }
    
    private static function createOrRefreshDatabase(DB $db) {
        $max = Out::promptNumber('How many sentences are to be downloaded? (1 ~ 1000)', 1, 1000);
        $texts = array();
        while (true) {
            Out::writeln('Fetching data from a new random entry...');
            $html = @file_get_contents(self::$wikipediaUrl);
            if ($html === false) {
                $error = error_get_last();
                throw new \Exception($error['message']);
            }
            $dom = new \DOMDocument;
            @$dom->loadHTML($html);
            $xpath = new \DOMXPath($dom);
            foreach ($xpath->query('//div[@id="mw-content-text"]/p[1]') as $node) {
                $value = trim(preg_replace(
                    array('/\(.*?\)|\[.*?\]|\{.*?\}|\<.*?\>|"/s', '/\s++/'),
                    array('', ' '),
                    $node->nodeValue
                ));
                foreach (explode('.', $value) as $sentence) {
                    $sentence = trim($sentence) . '.';
                    if (preg_match('/\A[-,.a-z0-9 ]{5,70}\z/i', $sentence)) {
                        Out::writeln('[ Sentence ] ' . $sentence);
                        $texts[] = $sentence;
                        if (isset($texts[$max - 1])) {
                            break 3;
                        }
                    }
                }
            }
        }
        Out::writeln('Inserting data into database...');
        $dt = date_create('now', timezone_open(self::$timeZone));
        $db->insertSentences($texts, $dt->format('Y-m-d H:i:s'));
        Out::writeln('Done.');
        Out::writeln();
    }
    
    private static function quit() {
        if (Out::promptYN('Really quit?')) {
            exit(0);
        }
    }
    
}

Application::run();