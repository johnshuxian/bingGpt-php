<?php

namespace App\Models;

use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;
use Laravel\Sanctum\NewAccessToken;

class Staff extends Authenticatable
{
    use HasFactory;
    use HasApiTokens;

    protected $expiresAt;

    /**
     * 删除 当前用户所有 sanctum token.
     *
     * @return mixed
     */
    public function destroySanctumTokens()
    {
        return $this->tokens()->delete();
    }

    public function setEx(Carbon $carbon)
    {
        $this->expiresAt = $carbon;

        return $this;
    }

    public function createToken(string $name, array $abilities = ['*'], DateTimeInterface $expiresAt = null): NewAccessToken
    {
        $token = $this->tokens()->create([
            'name'       => $name,
            'token'      => hash('sha256', $plainTextToken = Str::random(40)),
            'abilities'  => $abilities,
            'expires_at' => $this->expiresAt ?: $expiresAt,
        ]);

        return new NewAccessToken($token, $token->getKey() . '|' . $plainTextToken);
    }
}
