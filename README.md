PHP Type Trainer
================

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

Usage
------

<del>説明書なんて無かった</del>

Qiita記事書いたので一応載せておきます  
説明書とは呼べないレベルの駄文ばかりですがお赦しください

**[Qiita - DPマッチング法を用いた英文タイピングトレーナー](http://qiita.com/mpyw/items/1012c185fc2540699ee6)**

ToDo
-----

- 正規表現で可能な限り高精度で英文一文を切り出そうと試行錯誤してたけど英語にピリオドで終わる省略形が多すぎてわりと妥協してるからいつか直す（直すとは言ってない）
