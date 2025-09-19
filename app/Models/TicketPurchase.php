<?php

// TicketPurchase.php

declare(strict_types=1);

namespace App\Models;

use App\DbConnection;

/*
 * 本来とは少し違うが、今回はここに「チケット購入」に関する処理をまとめる
 */
class TicketPurchase
{
    // tokenから情報を取得
    public static function getByToken(string $token): array|false
    {
        try {
            $dbh = DbConnection::get();

            // プリペアドステートメント
            $sql = 'SELECT * FROM ticket_purchases WHERE token = :token;';
            $pre = $dbh->prepare($sql);
            //
            $pre->bindValue(':token', $token, \PDO::PARAM_STR);
            //
            $pre->execute();
            $datum = $pre->fetch();
        } catch (\PDOException $e) {
            // XXX 暫定: 本来はlogに出力する & エラーページを出力する
            echo $e->getMessage();
            exit;
        }

        return $datum;
    }

    // 全件取得
    public static function getAll(): array
    {
        // DB接続情報
        try {
            $dbh = DbConnection::get();

            // プリペアドステートメント
            $stmt = $dbh->prepare('SELECT * FROM ticket_purchases ORDER BY created_at DESC;');
            $stmt->execute();
            $list = $stmt->fetchAll();
        } catch (\PDOException $e) {
            // XXX 暫定: 本来はlogに出力する & エラーページを出力する
            echo $e->getMessage();
            exit;
        }

        return $list;
    }
}
