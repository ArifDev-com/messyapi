<?php


/**
 * @return App\Models\User|null
 */
function user() {
    return \Illuminate\Support\Facades\Auth::user();
}
function ucf($s) {
    return ucfirst($s);
}
function numberToWords($number)
{
    $ones = [
        0 => '', 1 => 'One', 2 => 'Two', 3 => 'Three', 4 => 'Four',
        5 => 'Five', 6 => 'Six', 7 => 'Seven', 8 => 'Eight', 9 => 'Nine',
        10 => 'Ten', 11 => 'Eleven', 12 => 'Twelve', 13 => 'Thirteen',
        14 => 'Fourteen', 15 => 'Fifteen', 16 => 'Sixteen',
        17 => 'Seventeen', 18 => 'Eighteen', 19 => 'Nineteen'
    ];
    $tens = [
        2 => 'Twenty', 3 => 'Thirty', 4 => 'Forty',
        5 => 'Fifty', 6 => 'Sixty', 7 => 'Seventy',
        8 => 'Eighty', 9 => 'Ninety'
    ];
    $units = ['', 'Thousand', 'Lakh', 'Crore'];
    if ($number == 0) {
        return 'Zero';
    }
    $words = [];
    if ($number >= 10000000) {
        $crore = floor($number / 10000000);
        $words[] = numberToWords($crore) . ' Crore';
        $number %= 10000000;
    }
    if ($number >= 100000) {
        $lakh = floor($number / 100000);
        $words[] = numberToWords($lakh) . ' Lakh';
        $number %= 100000;
    }
    if ($number >= 1000) {
        $thousand = floor($number / 1000);
        $words[] = numberToWords($thousand) . ' Thousand';
        $number %= 1000;
    }
    if ($number >= 100) {
        $hundred = floor($number / 100);
        $words[] = $ones[$hundred] . ' Hundred';
        $number %= 100;
    }
    if ($number > 0) {
        if ($number < 20) {
            $words[] = $ones[$number];
        } else {
            $ten = floor($number / 10);
            $unit = $number % 10;
            $words[] = $tens[$ten] . ($unit ? ' ' . $ones[$unit] : '');
        }
    }
    return implode(' ', $words);
}
