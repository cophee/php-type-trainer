<?php

namespace mpyw\PhpTypeTrainer\lib;

final class Application {
    
    private static $maxRound = 10;
    private static $sqliteFilename = 'PhpTypeTrainerDb.db';
    private static $wikipediaUrl = 'http://simple.wikipedia.org/wiki/Special:Randompage';
    private static $timeZone = 'Asia/Tokyo';
    private static $captions = array(
        'expected' => 'Expected:',
        'actual'   => 'Actual:',
        'question' => 'Q.',
        'answer'   => 'A.',
        'good'     => '[ GOOD ]',
        'bad'      => '[ BAD ]',
        'finish'   => '[ FINISH ]',
        'welcome'  => '[ WELCOME ]',
        'menu'     => '[ MENU ]',
        'error'    => '[ ERROR ]',
    );
    private static $menu = array(
        1 => 'Start training',
        2 => 'View score ranking',
        3 => 'Create or refresh database',
        0 => 'Quit',
    );
    
    public static function run() {
        if (PHP_SAPI !== 'cli') {
            header('Content-Type: text/plain; charset=utf-8', true, 400);
            Util::errorln('This software requries PHP-CLI environment.');
            exit(1);
        }
        $opts = getopt('', array('test::'));
        if (isset($opts['test'])) {
            self::test();
            exit(0);
        }
        Util::writeln();
        Util::writeln(self::$captions['welcome']);
        $path = getcwd() . DIRECTORY_SEPARATOR . self::$sqliteFilename;
        if (!is_file($path) && !Util::promptYN("Create {$path} ? [Y/N]: ")) {
            exit(0);
        }
        Util::writeln();
        $db = new DB($path);
        try {
            while (true) {
                Util::writeln(self::$captions['menu']);
                foreach (self::$menu as $i => $text) {
                    Util::writeln("$i. $text");
                }
                $no = Util::prompt('Select by number: ');
                Util::writeln();
                if (isset(self::$menu[$no])) {
                    $callback = array(__CLASS__, str_replace(' ', '', self::$menu[$no]));
                    $callback($db);
                }
                Util::writeln();
            }
        } catch (\Exception $e) {
            Util::errorln($e->getMessage());
            exit(1);
        }
    }
    
    private static function test() {
        $intersects = Util::intersect(self::$captions, array('expected', 'actual'));
        $captions   = Util::appendWhiteSpaces(array_merge(array('blanks' => ''), $intersects));
        $match      = DPMatcher::match(Util::prompt("$captions[expected] "), Util::prompt("$captions[actual] "));
        Util::writeln("$captions[blanks] {$match['outputs']['underlines']}");
        Util::writeln("$captions[blanks] {$match['outputs']['errors']}");
        Util::writeln();
        Util::writeln(self::$captions[!$match['errcount'] ? 'good' : 'bad']);
        Util::writeln();
    }
    
    private static function startTraining(DB $db) {
        $texts = $db->getRandomSentences(self::$maxRound);
        if (!isset($texts[self::$maxRound - 1])) {
            Util::errorln('There are not enough sentences in the database.');
            return;
        }
        if (!Util::promptYN('Ready to start? [Y/N]: ')) {
            return;
        }
        Util::writeln();
        for ($i = 5; $i >= 0; --$i) {
            Util::writeln(str_repeat('.', $i));
            sleep(1);
        }
        $typed      = 0;
        $required   = 0;
        $error      = 0;
        $max        = count($texts);
        $intersects = Util::intersect(self::$captions, array('question', 'answer'));
        $captions   = Util::appendWhiteSpaces(array_merge(array('blanks' => ''), $intersects));
        $start      = microtime(true);
        foreach ($texts as $i => $question) {
            Util::writeln("$captions[question] $question");
            $answer = Util::prompt("$captions[answer] ");
            $match  = DPMatcher::match($question, $answer);
            Util::writeln("$captions[blanks] {$match['outputs']['underlines']}");
            Util::writeln("$captions[blanks] {$match['outputs']['errors']}");
            Util::writeln();
            Util::writeln(sprintf("[%02d / %02d] %s", $i + 1, $max, self::$captions[!$match['errcount'] ? 'good' : 'bad']));
            Util::writeln();
            $typed    += strlen($answer);
            $required += strlen($question);
            $error    += $match['errcount'];
        }
        $end   = microtime(true);
        $score = round(60 * max(0, $typed - 3 * $error) / ($end - $start));
        $acc   = round(100 * max(0, 1 - 3 * $error / max(1, $typed)));
        sleep(1);
        Util::writeln('[ FINISH ]');
        Util::writeln("Score: $score");
        Util::writeln("Accuracy: $acc%");
        $dt = date_create('now', timezone_open(self::$timeZone));
        $id = $db->insertScore($score, $acc, $dt->format('Y-m-d H:i:s'));
        foreach ($db->getRankings() as $i => $row) {
            if ($row['id'] == $id) {
                Util::writeln('New record! Ranked as #' . ($i + 1));
                break;
            }
        }
    }
    
    private static function viewScoreRanking(DB $db) {
        Util::writeln(' # | Score | ACC(%) | DateTime');
        foreach ($db->getRankings() as $i => $row) {
            Util::writeln(sprintf(
                '% 2d | % 5d | % 6.1f | %8s',
                $i + 1, $row['score'], $row['acc'], $row['date']
            ));
        }
    }
    
    private static function createOrRefreshDatabase(DB $db) {
        $max = Util::promptNumber('How many sentences are to be downloaded? (1 ~ 1000): ', 1, 1000);
        while (true) {
            Util::writeln('Fetching data from a new random entry...');
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
                    array('/\(.*?\)|\[.*?\]|\{.*?\}|\<.*?\>|(?<=\d),(?=\d{3})/s', '/\s++(?=[^a-z\d])/i', '/\s++/'),
                    array('', '', ' '),
                    $node->nodeValue
                ));
                foreach (explode('.', $value) as $sentence) {
                    $sentence = trim($sentence) . '.';
                    $wc = str_word_count($sentence);
                    if (preg_match('/\A[-,.a-z0-9 ]{8,65}\z/i', $sentence) && $wc >= 4 && $wc <= 25) {
                        Util::writeln('[ Sentence ] ' . $sentence);
                        $dt = date_create('now', timezone_open(self::$timeZone));
                        $db->insertSentence($sentence, $dt->format('Y-m-d H:i:s'));
                        if (!--$max) {
                            break 3;
                        }
                    }
                }
            }
        }
        Util::writeln('Done.');
        Util::writeln();
    }
    
    private static function quit() {
        if (Util::promptYN('Really quit? [Y/N]: ')) {
            Util::writeln();
            exit(0);
        }
    }
    
}