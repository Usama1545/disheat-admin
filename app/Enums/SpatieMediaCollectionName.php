<?php

namespace App\Enums;

use ArchTech\Enums\InvokableCases;
use ArchTech\Enums\Names;
use ArchTech\Enums\Values;

/**
 * @method static PROFILE_IMAGE()
 */
enum SpatieMediaCollectionName: string
{
    use InvokableCases, Values, Names;

    case PRODUCT_MAIN_IMAGE = 'main_image';
    case PRODUCT_ADDITIONAL_IMAGE = 'product_additional_image';
    case PRODUCT_VIDEO = 'product_video';
    case BANNER_IMAGE = 'banner_image';
    case ADDRESS_PROOF = 'address_proof';
    case VOIDED_CHECK = 'voided_check';
    case VARIANT_IMAGE = 'variant_image';

    case REVIEW_IMAGES = 'review_images';

    case DRIVER_LICENSE = 'driver_license';
    case VEHICLE_REGISTRATION = 'vehicle_registration';
    case PROFILE_IMAGE = 'profile_image';
    case FEATURED_SECTION_BACKGROUND_IMAGE = 'featured_section_background_image';
    // Featured Section responsive backgrounds
    case FEATURED_SECTION_BG_DESKTOP_4K = 'featured_section_bg_desktop_4k';
    case FEATURED_SECTION_BG_DESKTOP_FHD = 'featured_section_bg_desktop_fhd';
    case FEATURED_SECTION_BG_TABLET = 'featured_section_bg_tablet';
    case FEATURED_SECTION_BG_MOBILE = 'featured_section_bg_mobile';
    case CATEGORY_ICON = 'category_icon';
    case CATEGORY_ACTIVE_ICON = 'category_active_icon';
    case CATEGORY_BACKGROUND_IMAGE = 'category_background_image';
    case CATEGORY_BANNER = 'banner';
    case CATEGORY_IMAGE = 'image';
    case BUSINESS_LICENSE = 'business_license';
    case ARTICLES_OF_INCORPORATION = 'articles_of_incorporation';
    case NATIONAL_IDENTITY_CARD = 'national_identity_card';
    case AUTHORIZED_SIGNATURE = 'authorized_signature';
    case STORE_LOGO = 'store_logo';
    case STORE_BANNER = 'store_banner';

    case ITEM_RETURN_IMAGES = 'item_return_images';

    // Order item attachments (e.g., prescriptions or required documents)
    case ORDER_ITEM_ATTACHMENTS = 'order_item_attachments';

    case SWATCHE_IMAGE = 'swatche_image';
}
