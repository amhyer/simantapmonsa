<?php

namespace App\Services\Backup;

use JsonException;
use RuntimeException;
use UnexpectedValueException;

/**
 * Reads the backup JSON shape incrementally: a root object containing metadata
 * and arrays of row objects. Memory is bounded to one row plus a 64 KiB input
 * buffer rather than the complete upload.
 */
class BackupJsonStreamReader
{
    private const MAX_METADATA_BYTES = 65536;

    private const MAX_ROW_BYTES = 16777216;

    private string $buffer = '';

    private int $offset = 0;

    private bool $eof = false;

    /**
     * @param  resource  $stream
     */
    public function __construct(private $stream)
    {
        if (! is_resource($stream)) {
            throw new RuntimeException('Backup input must be a readable stream.');
        }
    }

    /**
     * @param  callable(string): void  $onTable
     * @param  callable(string, array<string|int, mixed>): void  $onRecord
     *
     * @throws JsonException
     */
    public function eachRecord(callable $onTable, callable $onRecord): void
    {
        $this->skipWhitespace();
        $this->expect('{');
        $this->skipWhitespace();
        $seen = [];

        if ($this->peek() === '}') {
            $this->next();
        } else {
            while (true) {
                $key = $this->readString();
                if (isset($seen[$key])) {
                    throw new UnexpectedValueException("Kunci duplikat dalam file backup: {$key}.");
                }
                $seen[$key] = true;

                $this->skipWhitespace();
                $this->expect(':');
                $this->skipWhitespace();

                if ($key === 'metadata') {
                    $this->readValue(self::MAX_METADATA_BYTES);
                } else {
                    $onTable($key);
                    $this->readTable($key, $onRecord);
                }

                $this->skipWhitespace();
                $separator = $this->next();
                if ($separator === '}') {
                    break;
                }
                if ($separator !== ',') {
                    throw new UnexpectedValueException('Format objek root backup tidak valid.');
                }
                $this->skipWhitespace();
            }
        }

        $this->skipWhitespace();
        if ($this->peek() !== null) {
            throw new UnexpectedValueException('Ada data tambahan setelah objek backup.');
        }
    }

    /**
     * @param  callable(string, array<string|int, mixed>): void  $onRecord
     *
     * @throws JsonException
     */
    private function readTable(string $table, callable $onRecord): void
    {
        $this->expect('[');
        $this->skipWhitespace();

        if ($this->peek() === ']') {
            $this->next();

            return;
        }

        while (true) {
            if ($this->peek() !== '{') {
                throw new UnexpectedValueException("Baris backup untuk {$table} harus berupa objek JSON.");
            }

            $record = $this->readValue(self::MAX_ROW_BYTES);
            if (! is_array($record)) {
                throw new UnexpectedValueException("Baris backup untuk {$table} tidak valid.");
            }
            $onRecord($table, $record);

            $this->skipWhitespace();
            $separator = $this->next();
            if ($separator === ']') {
                return;
            }
            if ($separator !== ',') {
                throw new UnexpectedValueException("Array backup untuk {$table} tidak valid.");
            }
            $this->skipWhitespace();
        }
    }

    /** @throws JsonException */
    private function readValue(?int $maximumBytes = null): mixed
    {
        $first = $this->peek();
        if ($first === null) {
            throw new UnexpectedValueException('Nilai JSON terpotong.');
        }

        if ($first === '"') {
            $raw = $this->readStringRaw($maximumBytes);
        } elseif ($first === '{' || $first === '[') {
            $raw = $this->readCompositeRaw($maximumBytes);
        } else {
            $raw = '';
            while (($character = $this->peek()) !== null
                && ! ctype_space($character)
                && ! in_array($character, [',', ']', '}'], true)) {
                $raw .= $this->next();
                $this->assertWithinLimit($raw, $maximumBytes);
            }
        }

        if ($raw === '') {
            throw new UnexpectedValueException('Nilai JSON kosong atau tidak valid.');
        }

        return json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    }

    private function assertWithinLimit(string $raw, ?int $maximumBytes): void
    {
        if ($maximumBytes !== null && strlen($raw) > $maximumBytes) {
            throw new UnexpectedValueException('Nilai JSON melewati batas ukuran yang diizinkan.');
        }
    }

    private function readString(): string
    {
        $value = json_decode($this->readStringRaw(), true, 512, JSON_THROW_ON_ERROR);
        if (! is_string($value)) {
            throw new UnexpectedValueException('Kunci objek JSON harus berupa teks.');
        }

        return $value;
    }

    private function readStringRaw(?int $maximumBytes = null): string
    {
        $this->expect('"');
        $raw = '"';
        $this->assertWithinLimit($raw, $maximumBytes);
        $escaped = false;

        while (($character = $this->next()) !== null) {
            $raw .= $character;
            $this->assertWithinLimit($raw, $maximumBytes);

            if ($escaped) {
                $escaped = false;

                continue;
            }
            if ($character === '\\') {
                $escaped = true;

                continue;
            }
            if ($character === '"') {
                return $raw;
            }
        }

        throw new UnexpectedValueException('String JSON tidak ditutup.');
    }

    private function readCompositeRaw(?int $maximumBytes = null): string
    {
        $opening = $this->next();
        $closing = $opening === '{' ? '}' : ']';
        $stack = [$closing];
        $raw = $opening;
        $this->assertWithinLimit($raw, $maximumBytes);
        $inString = false;
        $escaped = false;

        while ($stack !== []) {
            $character = $this->next();
            if ($character === null) {
                throw new UnexpectedValueException('Nilai JSON terpotong sebelum penutup.');
            }
            $raw .= $character;
            $this->assertWithinLimit($raw, $maximumBytes);

            if ($inString) {
                if ($escaped) {
                    $escaped = false;
                } elseif ($character === '\\') {
                    $escaped = true;
                } elseif ($character === '"') {
                    $inString = false;
                }

                continue;
            }

            if ($character === '"') {
                $inString = true;
            } elseif ($character === '{') {
                $stack[] = '}';
            } elseif ($character === '[') {
                $stack[] = ']';
            } elseif ($character === '}' || $character === ']') {
                if (array_pop($stack) !== $character) {
                    throw new UnexpectedValueException('Tanda kurung JSON tidak berpasangan.');
                }
            }
        }

        return $raw;
    }

    private function skipWhitespace(): void
    {
        while (($character = $this->peek()) !== null && ctype_space($character)) {
            $this->next();
        }
    }

    private function expect(string $expected): void
    {
        $actual = $this->next();
        if ($actual !== $expected) {
            throw new UnexpectedValueException("Format JSON tidak valid; diharapkan '{$expected}'.");
        }
    }

    private function peek(): ?string
    {
        if (! $this->ensureAvailable()) {
            return null;
        }

        return $this->buffer[$this->offset];
    }

    private function next(): ?string
    {
        if (! $this->ensureAvailable()) {
            return null;
        }

        return $this->buffer[$this->offset++];
    }

    private function ensureAvailable(): bool
    {
        if ($this->offset < strlen($this->buffer)) {
            return true;
        }
        if ($this->eof) {
            return false;
        }

        $chunk = fread($this->stream, 65536);
        if ($chunk === false) {
            throw new RuntimeException('Gagal membaca stream file backup.');
        }
        if ($chunk === '') {
            if (! feof($this->stream)) {
                throw new RuntimeException('Stream file backup tidak dapat dilanjutkan.');
            }
            $this->eof = true;
            $this->buffer = '';
            $this->offset = 0;

            return false;
        }

        $this->buffer = $chunk;
        $this->offset = 0;

        return true;
    }
}
