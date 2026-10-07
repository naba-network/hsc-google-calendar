<?php

declare(strict_types=1);

use Hsc\GoogleCalendar\Captcha;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CaptchaTest extends TestCase
{
    private const NOW = 1_800_000_000;

    public function testCorrectAnswerPasses(): void
    {
        $challenge = $this->captcha()->challenge(self::NOW, 4, 3);

        self::assertSame('4 + 3', $challenge['question']);
        self::assertTrue($this->captcha()->verify($challenge['token'], '7', self::NOW));
        self::assertTrue($this->captcha()->verify($challenge['token'], ' 7 ', self::NOW + 60));
    }

    #[DataProvider('wrongAnswerProvider')]
    public function testWrongAnswerFails(string $answer): void
    {
        $challenge = $this->captcha()->challenge(self::NOW, 4, 3);

        self::assertFalse($this->captcha()->verify($challenge['token'], $answer, self::NOW));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function wrongAnswerProvider(): array
    {
        return [
            'other number' => ['8'],
            'empty' => [''],
            'text' => ['sieben'],
            'concatenated' => ['43'],
        ];
    }

    public function testExpiredChallengeFails(): void
    {
        $challenge = $this->captcha()->challenge(self::NOW, 1, 1);

        self::assertFalse($this->captcha()->verify($challenge['token'], '2', self::NOW + 7201));
    }

    public function testTamperedTokenFails(): void
    {
        $token = $this->captcha()->challenge(self::NOW, 1, 1)['token'];
        $forged = '9:9:' . (self::NOW + 100) . substr($token, (int) strrpos($token, '.'));

        self::assertFalse($this->captcha()->verify($forged, '18', self::NOW));
    }

    public function testTokenFromOtherSecretFails(): void
    {
        $token = (new Captcha(static fn (): string => 'other-secret'))->challenge(self::NOW, 1, 1)['token'];

        self::assertFalse($this->captcha()->verify($token, '2', self::NOW));
    }

    #[DataProvider('garbageTokenProvider')]
    public function testGarbageTokenFails(string $token): void
    {
        self::assertFalse($this->captcha()->verify($token, '2', self::NOW));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function garbageTokenProvider(): array
    {
        return [
            'empty' => [''],
            'no signature' => ['1:1:1900000000'],
            'too many parts' => ['a.b.c'],
        ];
    }

    public function testRandomChallengeIsSolvable(): void
    {
        $challenge = $this->captcha()->challenge(self::NOW);
        [$a, $b] = array_map('intval', explode(' + ', $challenge['question']));

        self::assertGreaterThanOrEqual(1, $a);
        self::assertLessThanOrEqual(9, $b);
        self::assertTrue($this->captcha()->verify($challenge['token'], (string) ($a + $b), self::NOW));
    }

    private function captcha(): Captcha
    {
        return new Captcha(static fn (): string => 'test-secret');
    }
}
