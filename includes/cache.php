<?php

class FileCache
{
    private string $cacheDir;

    public function __construct(?string $cacheDir = null)
    {
        $this->cacheDir = $cacheDir ?? sys_get_temp_dir() . '/factupro_cache';
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }

    public function get(string $key): mixed
    {
        $file = $this->path($key);
        if (!file_exists($file)) {
            return null;
        }
        $raw = file_get_contents($file);
        if ($raw === false) {
            return null;
        }
        $data = json_decode($raw, true);
        if (!is_array($data) || $data['expires'] < time()) {
            @unlink($file);
            return null;
        }
        return $data['value'];
    }

    public function set(string $key, mixed $value, int $ttl = 300): void
    {
        file_put_contents(
            $this->path($key),
            json_encode(['value' => $value, 'expires' => time() + $ttl]),
            LOCK_EX
        );
    }

    public function remember(string $key, int $ttl, callable $callback): mixed
    {
        $cached = $this->get($key);
        if ($cached !== null) {
            return $cached;
        }
        $value = $callback();
        $this->set($key, $value, $ttl);
        return $value;
    }

    public function forget(string $key): void
    {
        @unlink($this->path($key));
    }

    public function flush(): void
    {
        foreach (glob($this->cacheDir . '/*.cache') ?: [] as $file) {
            @unlink($file);
        }
    }

    private function path(string $key): string
    {
        return $this->cacheDir . '/' . md5($key) . '.cache';
    }
}
