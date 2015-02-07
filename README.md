PHP Type Trainer
================

Version: 0.1.0

Summary
-------

- 1文字ずれたりして **「ああああ全部台無しだぁ」** 的なミスをする人を甘やかす英文タイプトレーナー
- 挿入誤りを`I`(Inserted)、脱落誤りを`D`(Deleted)、置換誤りを`S`(Substituted)として検出
- WikipediaのSimpleEnglish言語版からほどほどに入力しやすい英文を自動収集
- **何　故　か　P　H　P　で　書　い　た**

Installation
------------

コマンドライン版のPHPが実行可能な環境にて、以下のいずれかに従って起動してください

### 直接Pharアーカイブをダウンロードする

1. [build/PhpTypeTrainer.phar](https://github.com/mpyw/php-type-trainer/blob/master/build/PhpTypeTrainer.phar?raw=true) をクリックしてダウンロード
2. `php PhpTypeTrainer.phar` で起動

### GitHubからGitを用いてリポジトリのクローンを作成する

1. `git clone git://github.com/mpyw/php-type-trainer.git`
2. `php src/trainer.php` または `php build/PhpTypeTrainer.phar` で起動

### PackagistからComposerを用いてインストールする

1. `composer init`
2. Dependency設定時 `mpyw/php-type-trainer` を検索
3. `composer install`
4. `php vendor/mpyw/php-type-trainer/src/trainer.php` または  
`php vendor/mpyw/php-type-trainer/build/PhpTypeTrainer.phar` で起動


Usage
------

説明書なんて無かった