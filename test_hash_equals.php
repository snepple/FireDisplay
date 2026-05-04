<?php
$token1 = "test";
$token2 = 123;
var_dump($token1 !== $token2);
var_dump(hash_equals($token1, $token2));
