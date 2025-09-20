<?php

declare(strict_types=1);

require_once __DIR__ . "/../app/initialize.php";

use App\Config;
use App\DbConnection;
use App\Models\TicketPurchase;

// 入力を受け取る
$input = [
    "purchaser_name" => $_POST["purchaser_name"] ?? "",
    "email" => $_POST["email"] ?? "",
    "quantity" => $_POST["quantity"] ?? "",
];
// //
// $params = ["purchaser_name", "email", "quantity"];
// foreach ($params as $p) {
//     $input[$p] = $_POST[$p] ?? "";
// }

/* validate */
$errord = [];
// 氏名の入力
if ($input['purchaser_name'] === '') {
    $errord['purchaser_name'] = '氏名を入力してください';
}

// メアドの確認
if ($input["email"] === "") {
    $errord['email'] = 'メアドを入力してください';
} else {
    $email = $event_policy->emailValidate($input["email"]);
    $allow_duplicate_email = Config::get("event")["allow_duplicate_email"] ?? falae; // デフォルト不許可
    if (false === $email) {
        $errord['email'] = 'メアドのフォーマットがおかしいです';
    } elseif (false === $allow_duplicate_email) {
        // メアドの重複チェック
        $ticket = TicketPurchase::getByEmail($email);
        if (false !== $ticket) {
            $errord['email'] = 'このメールアドレスは既に使われています。別のメールアドレスを指定してください。';
        }
    }
}

// チケットの枚数
if ($input["quantity"] === "") {
    $errord['quantity'] = 'チケット枚数を入力してください';
} else {
    $quantity = filter_var($input["quantity"], FILTER_VALIDATE_INT);
    if (false === $quantity) {
        $errord['quantity'] = 'チケット枚数のフォーマットがおかしいです';
    } elseif (0 >= $quantity) {
        $errord['quantity'] = 'チケット枚数は正の値で入力してください';
    } elseif (false === $event_policy->canReserveQuantity($quantity)) {
        $max = Config::get('event')["quantity_policy"]["options"]["max_per_order"];
        $errord['quantity'] = "チケット枚数は{$max}枚以内でお願いします";
    }
}

// エラーがあった場合、入力フォームに戻す
if (count($errord) > 0) {
    // セッションにエラー内容と入力値を保存しておく
    $_SESSION['errord'] = $errord;
    $_SESSION['input'] = $input;
    // 入力フォームに戻す
    header('Location: index.php');
    exit;
}

// tokenの作成
$token = bin2hex(random_bytes(16));

/* DBへの登録 */
// DBハンドルの取得
try {
    $dbh = DbConnection::get();
} catch (\PDOException $e) {
    // XXX 暫定: 本来はlogに出力する & エラーページを出力する
    echo $e->getMessage();
    exit;
}

try {
    // データの登録
    // プリペアードステートメントを作る
    $sql = 'INSERT INTO ticket_purchases(email, purchaser_name, quantity, token, created_at, updated_at)
      VALUES(:email, :purchaser_name, :quantity, :token, :created_at, :updated_at);';
    $pre = $dbh->prepare($sql);
    // プレースホルダーに値をバインド
    $now = date("Y-m-d H:i:s");
    $pre->bindValue(':email', $input['email'], PDO::PARAM_STR);
    $pre->bindValue(':purchaser_name', $input['purchaser_name'], PDO::PARAM_STR);
    $pre->bindValue(':quantity', $input['quantity'], PDO::PARAM_INT);
    $pre->bindValue(':token', $token, PDO::PARAM_STR);
    $pre->bindValue(':created_at', $now, PDO::PARAM_STR);
    $pre->bindValue(':updated_at', $now, PDO::PARAM_STR);
    // 実行
    $pre->execute();
    // 次の処理用にid把握しておく
    $ticket_purchase_id = (int)$dbh->lastInsertId();

    // mailの送信
    $send_at = (new DateTimeImmutable())->format('Y-m-d H:i:s');
    $base_url = 'http://game.m-fr.net:8080/';
    $subject = '【チケット購入完了】チケット購入ありがとうございます';
    $body = $twig->render('ticket_purchase_complete.twig', [
        'purchaser_name' => $input['purchaser_name'],
        'quantity' => $input['quantity'],
        'base_url' => $base_url,
        'token' => $token,
    ]);
    // XXX 本当はここでmail送信をする

    // XXX 今回は実際のmail送信は書かないので「mailを送った履歴」DBへのinsertのみ
    $sql = 'INSERT INTO email_send_logs(ticket_purchase_id, email, purchaser_name, quantity, subject, body, sent_at, created_at, updated_at)
            VALUES (:ticket_purchase_id, :email, :purchaser_name, :quantity, :subject, :body, :sent_at, :created_at, :updated_at);';
    $pre = $dbh->prepare($sql);
    // 値をバインド
    $now = date("Y-m-d H:i:s");
    $pre->bindValue(':ticket_purchase_id', (int)$ticket_purchase_id, PDO::PARAM_INT);
    $pre->bindValue(':email', $input['email'], PDO::PARAM_STR);
    $pre->bindValue(':purchaser_name', $input['purchaser_name'], PDO::PARAM_STR);
    $pre->bindValue(':quantity', $input['quantity'], PDO::PARAM_INT);
    $pre->bindValue(':subject', $subject, PDO::PARAM_STR);
    $pre->bindValue(':body', $body, PDO::PARAM_STR);
    $pre->bindValue(':sent_at', $send_at, PDO::PARAM_STR);
    $pre->bindValue(':created_at', $now, PDO::PARAM_STR);
    $pre->bindValue(':updated_at', $now, PDO::PARAM_STR);
    // 実行
    $pre->execute();
} catch (Exception $e) {
    // XXX 暫定: 本来はlogに出力する & エラーページを出力する
    echo $e->getMessage();
    exit;
}

// 完了ページへのlocation
header('Location: fin_print.php');
