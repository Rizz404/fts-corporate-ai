<?php

/*
| Rules used by the application. Missing keys fall back to English
| (app.fallback_locale); add translations here when new rules are used.
*/

return [
    'after' => ':attribute は :date より後の日時にしてください。',
    'array' => ':attribute は一覧で指定してください。',
    'boolean' => ':attribute は「はい」か「いいえ」で指定してください。',
    'confirmed' => ':attribute の確認が一致しません。',
    'current_password' => '現在のパスワードが正しくありません。',
    'date' => ':attribute は有効な日付ではありません。',
    'different' => ':attribute は :other と異なる値にしてください。',
    'distinct' => ':attribute に重複した値があります。',
    'email' => ':attribute は有効なメールアドレスで入力してください。',
    'exists' => '選択された :attribute は無効です。',
    'in' => '選択された :attribute は無効です。',
    'integer' => ':attribute は整数で指定してください。',
    'max' => [
        'array' => ':attribute は :max 件以下にしてください。',
        'numeric' => ':attribute は :max 以下にしてください。',
        'string' => ':attribute は :max 文字以内で入力してください。',
    ],
    'min' => [
        'array' => ':attribute は :min 件以上選択してください。',
        'numeric' => ':attribute は :min 以上にしてください。',
        'string' => ':attribute は :min 文字以上で入力してください。',
    ],
    'prohibited' => ':attribute は指定できません。',
    'regex' => ':attribute の形式が正しくありません。',
    'required' => ':attribute は必須です。',
    'string' => ':attribute は文字列で入力してください。',
    'unique' => ':attribute はすでに使用されています。',

    'attributes' => [
        'email' => 'メールアドレス',
        'password' => 'パスワード',
        'name' => '氏名',
    ],
];
