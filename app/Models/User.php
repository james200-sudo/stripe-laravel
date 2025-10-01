<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'current_plan',
        'subscription_status',
        'stripe_customer_id',
        'stripe_subscription_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Vérifier si l'utilisateur a un plan actif
     */
    public function hasActivePlan(): bool
    {
        return $this->subscription_status === 'active';
    }

    /**
     * Vérifier si l'utilisateur est sur le plan gratuit
     */
    public function isOnFreePlan(): bool
    {
        return $this->current_plan === 'Free';
    }

    /**
     * Obtenir le plan actuel de l'utilisateur
     */
    public function getCurrentPlanDetails()
    {
        return Plan::where('name', $this->current_plan)->first();
    }

    /**
     * Mettre à jour le plan de l'utilisateur
     */
    public function updatePlan(string $planName, string $status = 'active')
    {
        $this->current_plan = $planName;
        $this->subscription_status = $status;
        $this->save();
    }
}