<?php

namespace mpyw\PhpTypeTrainer\lib;

final class Application
{
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
        1 => 'Fetch and store new sentences in wikipedia',
        2 => 'Show the number of stored sentences',
        3 => 'Start training',
        4 => 'View score ranking',
        8 => 'Clear stored sentences',
        9 => 'Clear score ranking',
        0 => 'Quit',
    );

    private static function test()
    {
        $intersects = Util::intersect(self::$captions, array('expected', 'actual'));
        $captions   = Util::appendWhiteSpaces(array_merge(array('blanks' => ''), $intersects));
        $match      = DPMatcher::match(Util::prompt("$captions[expected] "), Util::prompt("$captions[actual] "));
        Util::writeln("$captions[blanks] {$match['outputs']['underlines']}");
        Util::writeln("$captions[blanks] {$match['outputs']['errors']}");
        Util::writeln();
        Util::writeln(self::$captions[!$match['errcount'] ? 'good' : 'bad']);
        Util::writeln();
    }

    public static function run()
    {
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

    private static function fetchAndStoreNewSentencesInWikipedia(DB $db)
    {
        Util::writeln('How many sentences are to be downloaded?');
        Util::writeln('NOTE: Old sentences over 1000 are automatically deleted.');
        $max = Util::promptNumber('(0 ~ 1000): ', 0, 1000);
        if (!$max) {
            return;
        }
        while (true) {
            $error_count = 0;
            while (true) {
                Util::writeln('Fetching data from a new random entry...');
                $html = @file_get_contents(self::$wikipediaUrl);
                if ($html === false) {
                    ++$error_count;
                    $error = error_get_last();
                    Util::errorln($error['message']);
                    if ($error_count >= 5) {
                        throw new \Exception('You are over error limit now. Please retry again later.');
                    }
                } else {
                    break;
                }
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
                $regex = '/[A-Z][a-z]{1,}+ [A-Z]\. [A-Z][a-z]{1,}+(*SKIP)(*FAIL)|(?<![DSJM]r\.)(?<!Mrs\.)(?<=[.?!])(?!(?:\S|\s[a-z]))/';
                foreach (preg_split($regex, $value, -1, PREG_SPLIT_NO_EMPTY) as $sentence) {
                    $sentence = trim($sentence);
                    $wc = str_word_count($sentence);
                    if (preg_match('/\A[-,.a-z0-9 ]{8,65}(?<=[.!?])\z/i', $sentence) && $wc >= 4 && $wc <= 25) {
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

    private static function showTheNumberOfStoredSentences(DB $db)
    {
        Util::writeln("The number of stored sentences: {$db->getSentencesCount()}");
    }

    private static function startTraining(DB $db)
    {
        $texts = $db->getRandomSentences(self::$maxRound);
        if (!isset($texts[self::$maxRound - 1])) {
            Util::errorln('There are not enough sentences in the database.');
            Util::errorln('At least ' . self::$maxRound . ' sentences are required.');
            return;
        }
        if (!Util::promptYN('Ready to start? [Y/N]: ')) {
            return;
        }
        Util::writeln();
        for ($i = 3; $i >= 0; --$i) {
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
        $kpm   = round(60 * $typed / ($end - $start));
        $wpm   = round($kpm / 5);
        $epm   = round(60 * $error / ($end - $start));
        $score = round(60 * max(0, $typed - 3 * $error) / ($end - $start));
        sleep(1);
        Util::writeln('[ FINISH ]');
        Util::writeln("KPM:   $kpm");
        Util::writeln("WPM:   $wpm");
        Util::writeln("EPM:   $epm");
        Util::writeln("Score: $score");
        $dt = date_create('now', timezone_open(self::$timeZone));
        $id = $db->insertScore($kpm, $epm, $score, $dt->format('Y-m-d H:i:s'));
        foreach ($db->getRanking() as $i => $row) {
            if ($row['id'] == $id) {
                Util::writeln('New record! Ranked as #' . ($i + 1));
                break;
            }
        }
    }

    private static function viewScoreRanking(DB $db)
    {
        Util::writeln(' # | SCORE | KPM | WPM | EPM | DateTime');
        Util::writeln('--------------------------------------------------');
        foreach ($db->getRanking() as $i => $row) {
            Util::writeln(sprintf(
                '% 2d | % 5d | % 3d | % 3d | % 3d | %8s',
                $i + 1,  $row['score'], $row['kpm'], $row['wpm'], $row['epm'], $row['date']
            ));
        }
    }

    private static function clearStoredSentences(DB $db)
    {
        if (Util::promptYN('Really clear stored sentences? [Y/N]: ')) {
            $db->clearStoredSentences();
        }
    }

    private static function clearScoreRanking(DB $db)
    {
        if (Util::promptYN('Really clear score ranking? [Y/N]: ')) {
            $db->clearScoreRanking();
        }
    }

    private static function quit()
    {
        if (Util::promptYN('Really quit? [Y/N]: ')) {
            Util::writeln();
            exit(0);
        }
    }
}
