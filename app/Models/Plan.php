<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'monthly_price',
        'yearly_price',
        'perks',
        'is_active',
    ];

    protected $casts = [
        'perks' => 'array',
        'monthly_price' => 'decimal:2',
        'yearly_price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /**
     * Vérifier si le plan est gratuit
     */
    public function isFree(): bool
    {
        return $this->monthly_price == 0 && $this->yearly_price == 0;
    }

    /**
     * Vérifier si le plan est Enterprise
     */
    public function isEnterprise(): bool
    {
        return stripos($this->name, 'utility') !== false || 
               stripos($this->name, 'utilities') !== false ||
               stripos($this->name, 'enterprise') !== false;
    }

    /**
     * Calculer la réduction annuelle en valeur
     */
    public function getYearlyDiscountAttribute(): float
    {
        if ($this->monthly_price == 0 || $this->yearly_price == 0) {
            return 0;
        }

        $monthlyCost = $this->monthly_price * 12;
        return $monthlyCost - $this->yearly_price;
    }

    /**
     * Calculer la réduction annuelle en pourcentage
     */
    public function getYearlyDiscountPercentageAttribute(): float
    {
        if ($this->monthly_price == 0 || $this->yearly_price == 0) {
            return 0;
        }

        $monthlyCost = $this->monthly_price * 12;
        if ($monthlyCost == 0) {
            return 0;
        }

        return (($monthlyCost - $this->yearly_price) / $monthlyCost) * 100;
    }

    /**
     * Obtenir le prix formaté
     */
    public function getFormattedPrice(bool $isYearly = false): string
    {
        if ($this->isFree()) {
            return '$0';
        }

        if ($this->isEnterprise()) {
            return 'Custom';
        }

        $price = $isYearly ? $this->yearly_price : $this->monthly_price;
        return '$' . number_format($price, 0);
    }

    /**
     * Obtenir le prix pour une période donnée
     */
    public function getPriceValue(bool $isYearly = false): float
    {
        return $isYearly ? $this->yearly_price : $this->monthly_price;
    }

    /**
     * Scope pour les plans actifs
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope pour trier par prix
     */
    public function scopeOrderByPrice($query, $direction = 'asc')
    {
        return $query->orderBy('monthly_price', $direction);
    }
}