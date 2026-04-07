<?php
class Logger {
    public function __construct(private string $file) {}

    public function info(string $message, array $context = []): void {
        $this->write('INFO', $message, $context);
    }

    public function error(string $message, array $context = []): void {
        $this->write('ERROR', $message, $context);
    }

    private function write(string $level, string $message, array $context): void {
        $dir = dirname($this->file);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $line = sprintf(
            "[%s] %s %s %s\n",
            date('Y-m-d H:i:s'),
            $level,
            $message,
            $context ? json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : ''
        );
        file_put_contents($this->file, $line, FILE_APPEND);
    }
}
