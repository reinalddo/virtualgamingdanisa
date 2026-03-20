<?php
// Funciones para URL amigables (slug)
function slugify($text) {
    $text = preg_replace('~[\p{L}\p{Nd}]+~u', '-', $text);
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    $text = preg_replace('~[^-a-z0-9]+~', '', strtolower($text));
    $text = trim($text, '-');
    $text = preg_replace('~-+~', '-', $text);
    return $text ?: 'n-a';
}
