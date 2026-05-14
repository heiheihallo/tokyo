<?php

namespace App\Support;

class JapanTripReference
{
    public static function trip(): array
    {
        return [
            'slug' => 'japan-summer-2027',
            'name' => 'Oslo to Japan Summer 2027',
            'summary' => 'A parent and daughter summer planning workspace for Tokyo, Hakone, Kyoto, optional stopovers, budget scenarios, route notes, and booking priorities.',
            'starts_on' => '2027-06-26',
            'ends_on' => '2027-07-19',
            'currency_primary' => 'NOK',
            'currency_secondary' => 'JPY',
            'arrival_preference' => 'HND',
            'metadata' => [
                'recommended_window' => '2027-06-24 to 2027-07-27',
                'source_files' => [
                    'context/initial-deep-research-report.md',
                    'context/follow-up-report-and-app-planning.md',
                ],
                'notes' => ['No driving', 'Haneda preferred', 'Tokyo, Hakone, and Kyoto are the core bases'],
            ],
        ];
    }

    public static function variants(): array
    {
        return [
            [
                'slug' => 'value-copenhagen-stopover',
                'name' => 'Value with Copenhagen stopover',
                'budget_scenario' => 'value',
                'stopover_type' => 'copenhagen',
                'flight_strategy' => 'OSL > CPH stopover > HND, return HND > CPH > OSL',
                'description' => 'The 24-day reference itinerary with a low-friction Copenhagen stopover, Tokyo, Hakone, Kyoto, and a Tokyo return buffer.',
                'is_default' => true,
                'sort_order' => 10,
            ],
            [
                'slug' => 'value-airport-connection',
                'name' => 'Value airport connection only',
                'budget_scenario' => 'value',
                'stopover_type' => 'connection',
                'flight_strategy' => 'OSL > CPH > HND, return HND > CPH > OSL',
                'description' => 'Removes the intentional Copenhagen stay and keeps the trip focused on Japan with the same value hotel logic.',
                'is_default' => false,
                'sort_order' => 20,
            ],
            [
                'slug' => 'premium-airport-connection',
                'name' => 'Premium airport connection only',
                'budget_scenario' => 'premium',
                'stopover_type' => 'connection',
                'flight_strategy' => 'OSL > AMS/CDG > HND, return KIX > AMS/CDG > OSL',
                'description' => 'Open-jaw premium comfort concept with Tokyo Station Hotel, Hakone ryokan, Hotel Granvia Kyoto, and no intentional stopover.',
                'is_default' => false,
                'sort_order' => 30,
            ],
            [
                'slug' => 'premium-seoul-stopover',
                'name' => 'Premium with Seoul stopover',
                'budget_scenario' => 'premium',
                'stopover_type' => 'seoul',
                'flight_strategy' => 'OSL > CPH/AMS/CDG > HND, Japan > Seoul, ICN > Europe > OSL',
                'description' => 'A special-trip variant that replaces the final Tokyo return with Osaka/Kansai positioning and a short Seoul stopover.',
                'is_default' => false,
                'sort_order' => 40,
            ],
        ];
    }

    public static function sources(): array
    {
        return [
            ['source_key' => 'SAS_TOKYO_ROUTE', 'title' => 'SAS Tokyo route', 'source_type' => 'airline', 'authority' => 'SAS'],
            ['source_key' => 'SAS_CPH_STOPOVER', 'title' => 'SAS Copenhagen stopover', 'source_type' => 'airline', 'authority' => 'SAS'],
            ['source_key' => 'HND_ACCESS', 'title' => 'Haneda central Tokyo access', 'source_type' => 'rail', 'authority' => 'Tokyo Monorail'],
            ['source_key' => 'METS_AKIHABARA', 'title' => 'JR East Hotel Mets Premier Akihabara', 'source_type' => 'hotel', 'authority' => 'JR East Hotel Mets'],
            ['source_key' => 'TOKYO_RAMEN', 'title' => 'Tokyo Ramen Street', 'source_type' => 'food', 'authority' => 'Tokyo Station'],
            ['source_key' => 'TOKYO_FIRST_AVENUE', 'title' => 'First Avenue Tokyo Station', 'source_type' => 'food', 'authority' => 'Tokyo Station'],
            ['source_key' => 'TEAMLAB', 'title' => 'teamLab Planets', 'source_type' => 'activity', 'authority' => 'teamLab'],
            ['source_key' => 'DISNEY_CHILD', 'title' => 'Tokyo Disney child guidance', 'source_type' => 'activity', 'authority' => 'Tokyo Disney Resort'],
            ['source_key' => 'ROMANCECAR', 'title' => 'Odakyu Romancecar', 'source_type' => 'rail', 'authority' => 'Odakyu'],
            ['source_key' => 'HAKONE_FREEPASS', 'title' => 'Hakone Freepass', 'source_type' => 'rail', 'authority' => 'Odakyu'],
            ['source_key' => 'TEN_YU', 'title' => 'Hakone Kowakien Ten-yu', 'source_type' => 'hotel', 'authority' => 'Hakone Kowakien Ten-yu'],
            ['source_key' => 'VISCHIO_KYOTO', 'title' => 'Hotel Vischio Kyoto by Granvia', 'source_type' => 'hotel', 'authority' => 'Hotel Vischio Kyoto'],
            ['source_key' => 'NISHIKI', 'title' => 'Nishiki Market', 'source_type' => 'food', 'authority' => 'Kyoto tourism'],
            ['source_key' => 'NARA_DEER', 'title' => 'Nara Park deer guide', 'source_type' => 'activity', 'authority' => 'Nara tourism'],
            ['source_key' => 'DOTONBORI', 'title' => 'Dotonbori and Tombori River Cruise', 'source_type' => 'activity', 'authority' => 'Osaka tourism'],
            ['source_key' => 'SMARTEX_HAYATOKU', 'title' => 'SmartEX Hayatoku Nozomi fare', 'source_type' => 'rail', 'authority' => 'SmartEX'],
            ['source_key' => 'JNTO_SUMMER', 'title' => 'JNTO rainy season and Obon guidance', 'source_type' => 'seasonality', 'authority' => 'JNTO'],
        ];
    }

    public static function assets(): array
    {
        return [
            'accommodations' => [
                ['stable_key' => 'copenhagen-flex-hotel', 'name' => 'Airport linked or central Copenhagen hotel', 'city' => 'Copenhagen', 'country' => 'Denmark', 'neighborhood' => 'Kastrup or Indre By', 'breakfast_note' => 'included preferred', 'latitude' => 55.6761, 'longitude' => 12.5683],
                ['stable_key' => 'mets-akihabara', 'name' => 'JR East Hotel Mets Premier Akihabara', 'city' => 'Tokyo', 'country' => 'Japan', 'neighborhood' => 'Akihabara', 'breakfast_note' => 'buffet or cafe', 'latitude' => 35.6984, 'longitude' => 139.7730],
                ['stable_key' => 'hakone-ten-yu', 'name' => 'Hakone Kowakien Ten-yu', 'city' => 'Hakone', 'country' => 'Japan', 'neighborhood' => 'Kowakudani', 'breakfast_note' => 'included', 'dinner_note' => 'included', 'latitude' => 35.2402, 'longitude' => 139.0445],
                ['stable_key' => 'vischio-kyoto', 'name' => 'Hotel Vischio Kyoto by Granvia', 'city' => 'Kyoto', 'country' => 'Japan', 'neighborhood' => 'Kyoto Station', 'breakfast_note' => '100 plus item breakfast buffet', 'latitude' => 34.9837, 'longitude' => 135.7589],
                ['stable_key' => 'tokyo-station-hotel', 'name' => 'Tokyo Station Hotel', 'city' => 'Tokyo', 'country' => 'Japan', 'neighborhood' => 'Tokyo Station', 'breakfast_note' => 'premium breakfast', 'latitude' => 35.6814, 'longitude' => 139.7658],
                ['stable_key' => 'granvia-kyoto', 'name' => 'Hotel Granvia Kyoto', 'city' => 'Kyoto', 'country' => 'Japan', 'neighborhood' => 'Kyoto Station', 'breakfast_note' => 'family room friendly', 'latitude' => 34.9855, 'longitude' => 135.7584],
                ['stable_key' => 'seoul-flex-hotel', 'name' => 'Central Seoul hotel placeholder', 'city' => 'Seoul', 'country' => 'South Korea', 'neighborhood' => 'Myeongdong or Hongdae', 'breakfast_note' => 'included preferred', 'latitude' => 37.5665, 'longitude' => 126.9780],
            ],
            'activities' => [
                ['stable_key' => 'tivoli-flex', 'name' => 'Tivoli or Copenhagen harbor walk', 'city' => 'Copenhagen', 'country' => 'Denmark', 'rain_fit' => 'medium', 'age_fit' => 'high'],
                ['stable_key' => 'tokyo-station-first-avenue', 'name' => 'Tokyo Station First Avenue', 'city' => 'Tokyo', 'country' => 'Japan', 'area' => 'Tokyo Station', 'rain_fit' => 'high', 'age_fit' => 'high', 'latitude' => 35.6812, 'longitude' => 139.7671],
                ['stable_key' => 'asakusa-ueno', 'name' => 'Asakusa and Ueno light day', 'city' => 'Tokyo', 'country' => 'Japan', 'area' => 'Asakusa/Ueno', 'rain_fit' => 'medium', 'age_fit' => 'high', 'latitude' => 35.7148, 'longitude' => 139.7967],
                ['stable_key' => 'teamlab-planets', 'name' => 'teamLab Planets', 'city' => 'Tokyo', 'country' => 'Japan', 'area' => 'Toyosu', 'rain_fit' => 'high', 'age_fit' => 'high', 'prebooking_status' => 'recommended', 'latitude' => 35.6491, 'longitude' => 139.7899],
                ['stable_key' => 'tokyo-disney', 'name' => 'Tokyo Disney Resort day', 'city' => 'Urayasu', 'country' => 'Japan', 'area' => 'Maihama', 'rain_fit' => 'medium', 'age_fit' => 'high', 'prebooking_status' => 'required', 'latitude' => 35.6329, 'longitude' => 139.8804],
                ['stable_key' => 'shibuya-harajuku', 'name' => 'Shibuya or Harajuku light wander', 'city' => 'Tokyo', 'country' => 'Japan', 'area' => 'Shibuya/Harajuku', 'rain_fit' => 'medium', 'age_fit' => 'medium', 'latitude' => 35.6595, 'longitude' => 139.7005],
                ['stable_key' => 'hakone-loop', 'name' => 'Hakone loop', 'city' => 'Hakone', 'country' => 'Japan', 'rain_fit' => 'low', 'age_fit' => 'high', 'latitude' => 35.2324, 'longitude' => 139.1069],
                ['stable_key' => 'nishiki-market', 'name' => 'Nishiki Market', 'city' => 'Kyoto', 'country' => 'Japan', 'area' => 'Central Kyoto', 'rain_fit' => 'high', 'age_fit' => 'high', 'latitude' => 35.0050, 'longitude' => 135.7647],
                ['stable_key' => 'kyoto-temple-morning', 'name' => 'Kyoto temple district morning', 'city' => 'Kyoto', 'country' => 'Japan', 'rain_fit' => 'medium', 'age_fit' => 'medium'],
                ['stable_key' => 'arashiyama', 'name' => 'Arashiyama side trip', 'city' => 'Kyoto', 'country' => 'Japan', 'area' => 'Arashiyama', 'rain_fit' => 'low', 'age_fit' => 'high', 'latitude' => 35.0094, 'longitude' => 135.6668],
                ['stable_key' => 'nara-park', 'name' => 'Nara Park', 'city' => 'Nara', 'country' => 'Japan', 'rain_fit' => 'medium', 'age_fit' => 'high', 'latitude' => 34.6851, 'longitude' => 135.8430],
                ['stable_key' => 'dotonbori', 'name' => 'Dotonbori and Tombori River Cruise', 'city' => 'Osaka', 'country' => 'Japan', 'rain_fit' => 'medium', 'age_fit' => 'high', 'latitude' => 34.6687, 'longitude' => 135.5010],
                ['stable_key' => 'kyoto-kid-choice', 'name' => 'Kid choice day', 'city' => 'Kyoto', 'country' => 'Japan', 'rain_fit' => 'high', 'age_fit' => 'high'],
                ['stable_key' => 'odaiba-toyosu', 'name' => 'Toyosu or Odaiba outing', 'city' => 'Tokyo', 'country' => 'Japan', 'area' => 'Tokyo Bay', 'rain_fit' => 'high', 'age_fit' => 'high', 'latitude' => 35.6248, 'longitude' => 139.7752],
            ],
            'food_spots' => [
                ['stable_key' => 'tokyo-ramen-street', 'name' => 'Tokyo Ramen Street', 'city' => 'Tokyo', 'country' => 'Japan', 'area' => 'Tokyo Station', 'default_meal_type' => 'lunch', 'latitude' => 35.6812, 'longitude' => 139.7671],
                ['stable_key' => 'station-snacks', 'name' => 'Station snacks and food halls', 'city' => 'Tokyo', 'country' => 'Japan', 'default_meal_type' => 'snack'],
                ['stable_key' => 'convenience-store-backup', 'name' => 'Convenience store backup', 'country' => 'Japan', 'default_meal_type' => 'backup', 'fallback_type' => 'low_energy'],
                ['stable_key' => 'toyoso-lunch', 'name' => 'Toyosu lunch', 'city' => 'Tokyo', 'country' => 'Japan', 'area' => 'Toyosu', 'default_meal_type' => 'lunch'],
                ['stable_key' => 'nishiki-tasting', 'name' => 'Nishiki tasting lunch', 'city' => 'Kyoto', 'country' => 'Japan', 'area' => 'Nishiki', 'default_meal_type' => 'lunch'],
                ['stable_key' => 'osaka-street-food', 'name' => 'Osaka street food', 'city' => 'Osaka', 'country' => 'Japan', 'area' => 'Dotonbori', 'default_meal_type' => 'dinner'],
                ['stable_key' => 'ryokan-dining', 'name' => 'Ryokan breakfast and dinner', 'city' => 'Hakone', 'country' => 'Japan', 'default_meal_type' => 'half_board'],
            ],
            'transport_legs' => [
                ['stable_key' => 'osl-cph', 'mode' => 'flight', 'route_label' => 'Oslo to Copenhagen', 'origin' => 'OSL', 'destination' => 'CPH', 'duration_label' => 'short haul'],
                ['stable_key' => 'cph-hnd', 'mode' => 'flight', 'route_label' => 'Copenhagen to Tokyo Haneda', 'origin' => 'CPH', 'destination' => 'HND', 'duration_label' => 'long haul'],
                ['stable_key' => 'haneda-tokyo', 'mode' => 'rail', 'route_label' => 'Haneda to central Tokyo', 'origin' => 'HND', 'destination' => 'Tokyo', 'duration_label' => 'about 19 minutes to Tokyo Station by monorail/JR connection'],
                ['stable_key' => 'tokyo-hakone-romancecar', 'mode' => 'rail', 'route_label' => 'Shinjuku to Hakone Yumoto by Romancecar', 'origin' => 'Shinjuku', 'destination' => 'Hakone Yumoto', 'duration_label' => 'about 80 minutes'],
                ['stable_key' => 'hakone-freepass-loop', 'mode' => 'rail_boat_ropeway', 'route_label' => 'Hakone Freepass loop', 'origin' => 'Hakone', 'destination' => 'Hakone', 'duration_label' => 'full day'],
                ['stable_key' => 'hakone-kyoto', 'mode' => 'rail', 'route_label' => 'Hakone/Odawara to Kyoto by shinkansen', 'origin' => 'Odawara', 'destination' => 'Kyoto', 'duration_label' => 'intercity transfer'],
                ['stable_key' => 'kyoto-nara-return', 'mode' => 'rail', 'route_label' => 'Kyoto to Nara return', 'origin' => 'Kyoto', 'destination' => 'Nara', 'duration_label' => 'day trip'],
                ['stable_key' => 'kyoto-osaka-return', 'mode' => 'rail', 'route_label' => 'Kyoto to Osaka return', 'origin' => 'Kyoto', 'destination' => 'Osaka', 'duration_label' => 'day trip'],
                ['stable_key' => 'kyoto-tokyo-shinkansen', 'mode' => 'rail', 'route_label' => 'Kyoto to Tokyo by Nozomi', 'origin' => 'Kyoto', 'destination' => 'Tokyo', 'duration_label' => 'shinkansen transfer'],
                ['stable_key' => 'hnd-cph-osl', 'mode' => 'flight', 'route_label' => 'Tokyo Haneda to Oslo via Copenhagen', 'origin' => 'HND', 'destination' => 'OSL', 'duration_label' => 'long haul return'],
            ],
        ];
    }

    public static function dayTemplates(): array
    {
        return [
            [1, '2027-06-26', 'Oslo > Copenhagen', 'Travel to Copenhagen', ['travel', 'stay'], 'high', [2500, 4200], [4000, 6500], ['SAS_TOKYO_ROUTE', 'SAS_CPH_STOPOVER'], ['copenhagen-flex-hotel'], ['osl-cph'], ['tivoli-flex'], ['convenience-store-backup']],
            [2, '2027-06-27', 'Copenhagen', 'Low effort Copenhagen day', ['stay', 'activity'], 'medium', [1500, 2500], [3000, 5000], ['SAS_CPH_STOPOVER'], ['copenhagen-flex-hotel'], [], ['tivoli-flex'], ['station-snacks']],
            [3, '2027-06-28', 'Copenhagen > Tokyo', 'Long haul to Tokyo Haneda', ['travel', 'stay'], 'high', [3000, 4800], [6000, 9000], ['HND_ACCESS', 'METS_AKIHABARA', 'SAS_TOKYO_ROUTE'], ['mets-akihabara'], ['cph-hnd', 'haneda-tokyo'], [], ['convenience-store-backup']],
            [4, '2027-06-29', 'Tokyo', 'Tokyo Station first easy day', ['stay', 'activity'], 'medium', [1400, 2300], [3200, 5200], ['METS_AKIHABARA', 'TOKYO_RAMEN', 'TOKYO_FIRST_AVENUE'], ['mets-akihabara'], [], ['tokyo-station-first-avenue'], ['tokyo-ramen-street', 'station-snacks']],
            [5, '2027-06-30', 'Tokyo', 'Asakusa, Ueno, and Akihabara', ['stay', 'activity'], 'low', [1400, 2300], [3200, 5200], ['METS_AKIHABARA'], ['mets-akihabara'], [], ['asakusa-ueno'], ['station-snacks']],
            [6, '2027-07-01', 'Tokyo', 'teamLab Planets and Toyosu', ['stay', 'activity'], 'high', [1800, 2800], [3600, 5600], ['TEAMLAB', 'METS_AKIHABARA'], ['mets-akihabara'], [], ['teamlab-planets'], ['toyoso-lunch']],
            [7, '2027-07-02', 'Tokyo', 'Tokyo Disney Resort day', ['stay', 'activity'], 'high', [2300, 3800], [4200, 6800], ['DISNEY_CHILD', 'METS_AKIHABARA'], ['mets-akihabara'], [], ['tokyo-disney'], ['station-snacks']],
            [8, '2027-07-03', 'Tokyo', 'Shibuya or Harajuku recovery day', ['stay', 'activity'], 'low', [1400, 2300], [3200, 5200], ['METS_AKIHABARA'], ['mets-akihabara'], [], ['shibuya-harajuku'], ['station-snacks']],
            [9, '2027-07-04', 'Tokyo', 'Rain-safe flex day', ['stay', 'activity'], 'low', [1300, 2200], [3000, 5000], ['TOKYO_FIRST_AVENUE', 'METS_AKIHABARA', 'JNTO_SUMMER'], ['mets-akihabara'], [], ['tokyo-station-first-avenue'], ['tokyo-ramen-street']],
            [10, '2027-07-05', 'Tokyo > Hakone', 'Romancecar to Hakone ryokan', ['travel', 'stay'], 'high', [2600, 4200], [5200, 8500], ['ROMANCECAR', 'TEN_YU', 'HAKONE_FREEPASS'], ['hakone-ten-yu'], ['tokyo-hakone-romancecar'], [], ['ryokan-dining']],
            [11, '2027-07-06', 'Hakone', 'Hakone loop and ryokan day', ['stay', 'activity'], 'medium', [1800, 3000], [4200, 7000], ['HAKONE_FREEPASS', 'TEN_YU'], ['hakone-ten-yu'], ['hakone-freepass-loop'], ['hakone-loop'], ['ryokan-dining']],
            [12, '2027-07-07', 'Hakone > Kyoto', 'Transfer to Kyoto Station base', ['travel', 'stay'], 'high', [2200, 3200], [4200, 6200], ['VISCHIO_KYOTO', 'SMARTEX_HAYATOKU'], ['vischio-kyoto'], ['hakone-kyoto'], [], ['station-snacks']],
            [13, '2027-07-08', 'Kyoto', 'Nishiki and central Kyoto', ['stay', 'activity'], 'medium', [1500, 2400], [3400, 5600], ['VISCHIO_KYOTO', 'NISHIKI'], ['vischio-kyoto'], [], ['nishiki-market'], ['nishiki-tasting']],
            [14, '2027-07-09', 'Kyoto', 'Temple district morning', ['stay', 'activity'], 'low', [1400, 2200], [3200, 5200], ['VISCHIO_KYOTO'], ['vischio-kyoto'], [], ['kyoto-temple-morning'], ['station-snacks']],
            [15, '2027-07-10', 'Kyoto', 'Arashiyama side trip', ['stay', 'activity'], 'low', [1500, 2400], [3400, 5600], ['VISCHIO_KYOTO'], ['vischio-kyoto'], [], ['arashiyama'], ['station-snacks']],
            [16, '2027-07-11', 'Kyoto > Nara > Kyoto', 'Nara Park day trip', ['travel', 'activity', 'stay'], 'medium', [1600, 2500], [3500, 5800], ['NARA_DEER', 'VISCHIO_KYOTO'], ['vischio-kyoto'], ['kyoto-nara-return'], ['nara-park'], ['station-snacks']],
            [17, '2027-07-12', 'Kyoto', 'Rest, laundry, and buffer day', ['stay', 'activity'], 'low', [1200, 2000], [3000, 5000], ['VISCHIO_KYOTO'], ['vischio-kyoto'], [], ['kyoto-kid-choice'], ['station-snacks']],
            [18, '2027-07-13', 'Kyoto > Osaka > Kyoto', 'Osaka and Dotonbori day trip', ['travel', 'activity', 'stay'], 'medium', [1700, 2800], [3600, 6000], ['DOTONBORI', 'VISCHIO_KYOTO'], ['vischio-kyoto'], ['kyoto-osaka-return'], ['dotonbori'], ['osaka-street-food']],
            [19, '2027-07-14', 'Kyoto', 'Kid choice Kyoto day', ['stay', 'activity'], 'low', [1300, 2200], [3000, 5200], ['VISCHIO_KYOTO'], ['vischio-kyoto'], [], ['kyoto-kid-choice'], ['station-snacks']],
            [20, '2027-07-15', 'Kyoto', 'Final light Kyoto day', ['stay', 'activity'], 'low', [1200, 2000], [3000, 5000], ['VISCHIO_KYOTO'], ['vischio-kyoto'], [], ['kyoto-kid-choice'], ['station-snacks']],
            [21, '2027-07-16', 'Kyoto > Tokyo', 'Shinkansen back to Tokyo', ['travel', 'stay'], 'high', [2000, 3000], [3800, 6200], ['SMARTEX_HAYATOKU', 'METS_AKIHABARA'], ['mets-akihabara'], ['kyoto-tokyo-shinkansen'], [], ['station-snacks']],
            [22, '2027-07-17', 'Tokyo', 'Final full Tokyo city day', ['stay', 'activity'], 'low', [1500, 2500], [3400, 5600], ['METS_AKIHABARA', 'TEAMLAB'], ['mets-akihabara'], [], ['odaiba-toyosu'], ['toyoso-lunch']],
            [23, '2027-07-18', 'Tokyo', 'Weather, shopping, and packing buffer', ['stay', 'activity'], 'low', [1300, 2200], [3000, 5200], ['TOKYO_RAMEN', 'METS_AKIHABARA'], ['mets-akihabara'], [], ['tokyo-station-first-avenue'], ['tokyo-ramen-street']],
            [24, '2027-07-19', 'Tokyo > Oslo', 'Fly home via Copenhagen', ['travel'], 'high', [2500, 4200], [5000, 8500], ['HND_ACCESS', 'SAS_TOKYO_ROUTE'], [], ['hnd-cph-osl'], [], ['station-snacks']],
        ];
    }

    public static function routePoints(): array
    {
        return [
            ['stable_key' => 'osl', 'name' => 'Oslo Airport', 'category' => 'airport', 'latitude' => 60.1976, 'longitude' => 11.1004, 'sequence' => 1, 'route_group' => 'international'],
            ['stable_key' => 'cph', 'name' => 'Copenhagen', 'category' => 'stopover', 'latitude' => 55.6761, 'longitude' => 12.5683, 'sequence' => 2, 'route_group' => 'international'],
            ['stable_key' => 'hnd', 'name' => 'Tokyo Haneda', 'category' => 'airport', 'latitude' => 35.5494, 'longitude' => 139.7798, 'sequence' => 3, 'route_group' => 'international'],
            ['stable_key' => 'tokyo', 'name' => 'Tokyo', 'category' => 'base', 'latitude' => 35.6812, 'longitude' => 139.7671, 'sequence' => 4, 'route_group' => 'japan'],
            ['stable_key' => 'hakone', 'name' => 'Hakone', 'category' => 'base', 'latitude' => 35.2324, 'longitude' => 139.1069, 'sequence' => 5, 'route_group' => 'japan'],
            ['stable_key' => 'kyoto', 'name' => 'Kyoto', 'category' => 'base', 'latitude' => 34.9858, 'longitude' => 135.7588, 'sequence' => 6, 'route_group' => 'japan'],
            ['stable_key' => 'nara', 'name' => 'Nara', 'category' => 'day_trip', 'latitude' => 34.6851, 'longitude' => 135.8430, 'sequence' => 7, 'route_group' => 'kansai'],
            ['stable_key' => 'osaka', 'name' => 'Osaka', 'category' => 'day_trip', 'latitude' => 34.6687, 'longitude' => 135.5010, 'sequence' => 8, 'route_group' => 'kansai'],
        ];
    }
}
