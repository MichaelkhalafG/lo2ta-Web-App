<?php

/*
| Unit features approved on 2026-11-17 (PLAN 2.2.5). applies_to: main types; 'all' expands to the four.
| The seeder adds missing codes only; the dashboard owns the rows after that.
*/

$all = ['residential', 'commercial', 'administrative', 'land'];
$noLand = ['residential', 'commercial', 'administrative'];
$res = ['residential'];

return [
    ['unit', 'private_garden', 'حديقة خاصة', 'Private garden', $res],
    ['unit', 'roof', 'روف', 'Roof', $res],
    ['unit', 'private_pool', 'حمام سباحة خاص', 'Private pool', $res],
    ['unit', 'balcony', 'بلكونة / تراس', 'Balcony / terrace', ['residential', 'administrative']],
    ['unit', 'nanny_room', 'غرفة مربية', "Nanny's room", $res],
    ['unit', 'laundry_room', 'غرفة غسيل', 'Laundry room', $res],
    ['unit', 'storage_room', 'غرفة تخزين', 'Storage room', $noLand],
    ['unit', 'kitchen_installed', 'مطبخ مركّب', 'Kitchen installed', $res],
    ['unit', 'acs_installed', 'تكييفات مركّبة', 'ACs installed', $noLand],
    ['unit', 'central_ac', 'تكييف مركزي', 'Central A/C', $noLand],
    ['unit', 'smart_home', 'نظام منزل ذكي', 'Smart home system', $res],
    ['unit', 'natural_gas', 'غاز طبيعي', 'Natural gas', $res],
    ['unit', 'corner_unit', 'وحدة ناصية', 'Corner unit', $all],
    ['unit', 'private_garage', 'جراج خاص', 'Private garage', $res],
    ['unit', 'parking_spot', 'مكان ركن مخصص', 'Dedicated parking spot', $noLand],
    ['building', 'elevator', 'أسانسير', 'Elevator', $noLand],
    ['building', 'backup_generator', 'مولد كهرباء احتياطي', 'Backup generator', $noLand],
    ['building', 'main_road_frontage', 'واجهة على طريق رئيسي', 'Main road frontage', ['commercial', 'administrative', 'land']],
    ['compound', 'security_24h', 'أمن وحراسة 24 ساعة', '24-hour security', $all],
    ['compound', 'cctv', 'كاميرات مراقبة', 'CCTV', $all],
    ['compound', 'shared_pool', 'حمام سباحة مشترك', 'Shared pool', $res],
    ['compound', 'clubhouse', 'كلوب هاوس', 'Clubhouse', $res],
    ['compound', 'gym', 'جيم', 'Gym', ['residential', 'administrative']],
    ['compound', 'kids_area', 'منطقة ألعاب أطفال', "Kids' play area", $res],
    ['compound', 'green_areas', 'مساحات خضراء ولاندسكيب', 'Green areas and landscaping', $all],
    ['compound', 'commercial_area', 'منطقة تجارية داخل المشروع', 'Commercial area inside the project', $all],
    ['compound', 'mosque', 'مسجد', 'Mosque', $all],
    ['compound', 'beach_access', 'شاطئ', 'Beach access', $res],
    ['compound', 'near_services', 'قريب من الخدمات', 'Near services', $all],
    ['compound', 'utilities_delivered', 'مرافق موصّلة', 'Utilities connected', ['land']],
    ['view', 'landscape_view', 'إطلالة على اللاندسكيب', 'Landscape view', $noLand],
    ['view', 'pool_view', 'إطلالة على حمام السباحة', 'Pool view', $res],
    ['view', 'lake_view', 'إطلالة على بحيرة / لاجون', 'Lake / lagoon view', $res],
    ['view', 'sea_view', 'إطلالة على البحر', 'Sea view', $res],
    ['view', 'golf_view', 'إطلالة على ملعب جولف', 'Golf view', $res],
];
