<?php

namespace mpyw\PhpTypeTrainer\lib;

final class DB {
    
    private static $maxStoredSentences = 1000;
    private static $maxStoredRanking = 10;
    
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
                kpm INTEGER NOT NULL,
                epm INTEGER NOT NULL,
                score INTEGER NOT NULL,
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
    
    public function insertSentence($text, $date) {
        $stmt = $this->prepare('REPLACE INTO sentence(text, date) VALUES(:text, :date)');
        $stmt->bindValue(':text', $text);
        $stmt->bindValue(':date', $date);
        $stmt->execute();
        $stmt = $this->prepare('
            DELETE FROM ranking WHERE id NOT IN (
                SELECT id FROM ranking ORDER BY date DESC LIMIT :limit
            )
        ');
        $stmt->bindValue(':limit', self::$maxStoredSentences, \PDO::PARAM_INT);
        $stmt->execute();
    }
    
    public function insertScore($kpm, $epm, $score, $date) {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->prepare('
                INSERT INTO ranking(kpm, epm, score, date)
                VALUES (:kpm, :epm, :score, :date)
            ');
            $stmt->bindValue(':kpm', $kpm, \PDO::PARAM_INT);
            $stmt->bindValue(':epm', $epm, \PDO::PARAM_INT);
            $stmt->bindValue(':score', $score, \PDO::PARAM_INT);
            $stmt->bindValue(':date', $date);
            $stmt->execute();
            $id = $this->pdo->lastInsertId();
            $stmt = $this->prepare('
                DELETE FROM ranking WHERE id NOT IN (
                    SELECT id FROM ranking ORDER BY score DESC, date ASC LIMIT :limit
                )
            ');
            $stmt->bindValue(':limit', self::$maxStoredRanking);
            $stmt->execute();
            $this->pdo->commit();
            return $id;
        } catch (\PDOException $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
    
    public function clearStoredSentences() {
        $this->pdo->exec('DELETE FROM sentence');
    }
    
    public function clearScoreRanking() {
        $this->pdo->exec('DELETE FROM ranking');
    }
    
    public function getSentencesCount() {
        return (int)$this->pdo->query('SELECT SUM(1) FROM sentence')->fetchColumn();
    }
    
    public function getRandomSentences($limit) {
        $stmt = $this->prepare('SELECT text FROM sentence ORDER BY RANDOM() LIMIT :limit');
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_COLUMN, 0);
    }
    
    public function getRanking() {
        return $this->pdo->query('
            SELECT id, kpm, round(kpm / 5) AS wpm, epm, score, date FROM ranking
            ORDER BY score DESC, kpm DESC, epm ASC, date ASC
        ')->fetchAll();
    }
    
}