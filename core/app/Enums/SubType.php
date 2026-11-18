<?php

namespace App\Enums;

/**
 * The 25 sub-types approved on 2026-11-17 (PLAN 2.2.3), each mapped to a main type, with the fields that apply.
 */
enum SubType: string
{
    use HasLabel;

    case Apartment = 'apartment';
    case GardenApartment = 'garden_apartment';
    case Duplex = 'duplex';
    case Penthouse = 'penthouse';
    case Studio = 'studio';
    case ServicedApartment = 'serviced_apartment';
    case Townhouse = 'townhouse';
    case TwinHouse = 'twin_house';
    case QuattroVilla = 'quattro_villa';
    case IVilla = 'i_villa';
    case StandaloneVilla = 'standalone_villa';
    case Chalet = 'chalet';
    case Cabin = 'cabin';
    case Shop = 'shop';
    case Pharmacy = 'pharmacy';
    case RestaurantCafe = 'restaurant_cafe';
    case Kiosk = 'kiosk';
    case Showroom = 'showroom';
    case Warehouse = 'warehouse';
    case Office = 'office';
    case Clinic = 'clinic';
    case ResidentialLand = 'residential_land';
    case CommercialLand = 'commercial_land';
    case AdministrativeLand = 'administrative_land';
    case IndustrialLand = 'industrial_land';

    public function mainType(): MainType
    {
        return match ($this) {
            self::Apartment, self::GardenApartment, self::Duplex, self::Penthouse, self::Studio,
            self::ServicedApartment, self::Townhouse, self::TwinHouse, self::QuattroVilla, self::IVilla,
            self::StandaloneVilla, self::Chalet, self::Cabin => MainType::Residential,
            self::Shop, self::Pharmacy, self::RestaurantCafe, self::Kiosk, self::Showroom,
            self::Warehouse => MainType::Commercial,
            self::Office, self::Clinic => MainType::Administrative,
            self::ResidentialLand, self::CommercialLand, self::AdministrativeLand,
            self::IndustrialLand => MainType::Land,
        };
    }

    public function hasFloor(): bool
    {
        return $this->mainType() !== MainType::Land && ! in_array($this, [
            self::GardenApartment, self::Townhouse, self::TwinHouse, self::QuattroVilla,
            self::StandaloneVilla, self::Cabin,
        ], true);
    }

    /** Bedrooms and bathrooms: residential only. */
    public function hasRooms(): bool
    {
        return $this->mainType() === MainType::Residential;
    }

    /**
     * Studios have no separate bedroom: bedrooms is fixed at 0.
     *
     * @return int<0, max>|null
     */
    public function fixedBedrooms(): ?int
    {
        return $this === self::Studio ? 0 : null;
    }

    public function hasGarden(): bool
    {
        return match ($this->mainType()) {
            MainType::Residential => ! in_array($this, [self::Penthouse, self::ServicedApartment], true),
            MainType::Commercial => $this === self::RestaurantCafe,
            default => false,
        };
    }

    public function hasRoof(): bool
    {
        return $this->mainType() === MainType::Residential && ! in_array($this, [
            self::GardenApartment, self::Studio, self::ServicedApartment, self::Cabin,
        ], true);
    }

    public function hasFinishing(): bool
    {
        return $this->mainType() !== MainType::Land;
    }

    /**
     * @return list<self>
     */
    public static function forMainType(MainType $type): array
    {
        return array_values(array_filter(self::cases(), static fn (self $s): bool => $s->mainType() === $type));
    }
}
