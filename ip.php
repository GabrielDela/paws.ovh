<?php

echo "<pre>";
echo "REMOTE_ADDR               : " . $_SERVER['REMOTE_ADDR'] . "\n";
echo "HTTP_CF_CONNECTING_IP     : " . ($_SERVER['HTTP_CF_CONNECTING_IP'] ?? 'Non défini') . "\n";
echo "HTTP_X_FORWARDED_FOR      : " . ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? 'Non défini') . "\n";
echo "</pre>";
