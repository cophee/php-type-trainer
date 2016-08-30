<?php

namespace mpyw\PhpTypeTrainer\lib;

final class DPMatcher
{
    private static $errorChars = array(
        'inserted'    => 'I',
        'deleted'     => 'D',
        'substituted' => 'S',
        'mixed'       => '*',
    );

    private function __construct()
    {
    }

    public static function match($expected, $input)
    {
        $ysize = strlen($expected);
        $xsize = strlen($input);
        $score = array(array(0));
        $vector = array(array(null));
        for ($y = 1; $y <= $ysize; ++$y) {
            $score[0][$y] = $y;
            $vector[0][$y] = array('x' => 0, 'y' => $y - 1);
        }
        for ($x = 1; $x <= $xsize; ++$x) {
            $score[$x][0] = $x;
            $vector[$x][0] = array('x' => $x - 1, 'y' => 0);
        }
        for ($x = 1; $x <= $xsize; ++$x) {
            for ($y = 1; $y <= $ysize; ++$y) {
                $incorrect = (int)(bool)strcasecmp($expected[$y - 1], $input[$x - 1]);
                $min = min(
                    $score[$x - 1][$y - 1] + 2 * $incorrect,
                    $score[$x - 1][$y] + $incorrect,
                    $score[$x][$y - 1] + $incorrect
                );
                if ($min === $score[$x - 1][$y - 1] + 2 * $incorrect) {
                    $mx = $x - 1;
                    $my = $y - 1;
                    $rate = 2;
                } else if ($min === $score[$x - 1][$y] + $incorrect) {
                    $mx = $x - 1;
                    $my = $y;
                    $rate = 1;
                } else {
                    $mx = $x;
                    $my = $y - 1;
                    $rate = 1;
                }
                $score[$x][$y] = $score[$mx][$my] + $incorrect * $rate;
                $vector[$x][$y] = array('x' => $mx, 'y' => $my);
            }
        }
        $result = array(
            'outputs' => array(
                'underlines' => ' ',
                'errors'     => ' ',
            ),
            'errcount' => 0,
        );
        for ($x = $xsize, $y = $ysize; $v = $vector[$x][$y]; $x = $v['x'], $y = $v['y']) {
            if ($x !== $v['x'] && $y === $v['y']) {
                $result['outputs']['underlines'][$x - 1] = '~';
                $result['outputs']['errors'][$x - 1] = self::$errorChars[
                    isset($result['outputs']['errors'][$x - 1]) && $result['outputs']['errors'][$x - 1] !== ' '
                    ? 'mixed' : 'inserted'
                ];
                ++$result['errcount'];
            } elseif ($x === $v['x'] && $y !== $v['y']) {
                $result['outputs']['errors'][$x] = self::$errorChars[
                    isset($result['outputs']['errors'][$x]) && $result['outputs']['errors'][$x] !== ' '
                    ? 'mixed' : 'deleted'
                ];
                ++$result['errcount'];
            } elseif ($x !== $v['x'] && $y !== $v['y'] && $score[$x][$y] !== $score[$v['x']][$v['y']]) {
                $result['outputs']['underlines'][$x - 1] = '~';
                $result['outputs']['errors'][$x - 1] = self::$errorChars[
                    isset($result['outputs']['errors'][$x - 1]) && $result['outputs']['errors'][$x - 1] !== ' '
                    ? 'mixed' : 'substituted'
                ];
                ++$result['errcount'];
            }
        }
        return $result;
    }
}
