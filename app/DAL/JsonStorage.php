<?php
/**
 * JsonStorage - Gestion lecture/ecriture des fichiers JSON avec verrouillage.
 */
class JsonStorage
{
    /**
     * Lit et decode un fichier JSON.
     * Retourne un tableau vide si le fichier n'existe pas ou est invalide.
     */
    public static function read(string $filePath): array
    {
        if (!file_exists($filePath)) {
            return [];
        }

        $handle = fopen($filePath, 'r');
        if (!$handle) {
            return [];
        }

        flock($handle, LOCK_SH);
        $content = stream_get_contents($handle);
        flock($handle, LOCK_UN);
        fclose($handle);

        $data = json_decode($content, true);
        return is_array($data) ? $data : [];
    }

    /**
     * Ecrit des donnees dans un fichier JSON avec verrouillage exclusif.
     */
    public static function write(string $filePath, array $data): bool
    {
        $dir = dirname($filePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $handle = fopen($filePath, 'c');
        if (!$handle) {
            return false;
        }

        flock($handle, LOCK_EX);
        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        fflush($handle);
        flock($handle, LOCK_UN);
        fclose($handle);

        return true;
    }
}
