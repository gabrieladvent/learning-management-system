<?php

namespace App\Support;

final class StudentQuotes
{
    /** @var list<string> */
    public const ALL = [
        'Sedikit demi sedikit, lama-lama menjadi bukit.',
        'Rajin pangkal pandai, hemat pangkal kaya.',
        'Berakit-rakit ke hulu, berenang-renang ke tepian. Bersakit-sakit dahulu, bersenang-senang kemudian.',
        'Di mana ada kemauan, di situ ada jalan.',
        'Belajar di waktu kecil bagai mengukir di atas batu.',
        'Tak ada rotan, akar pun jadi.',
        'Bersatu kita teguh, bercerai kita runtuh.',
        'Setiap orang menjadi guru, setiap rumah menjadi sekolah. — Ki Hajar Dewantara',
    ];

    public static function random(): string
    {
        return self::ALL[array_rand(self::ALL)];
    }
}
