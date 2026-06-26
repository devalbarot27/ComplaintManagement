<?php
require_once __DIR__ . '/../pdo_obconn.php';

$segments = [
    'GENERAL ENGINEERING - MACHINING',
    'TEXTILES',
    'PAPER AND PULP',
    'FOUNDRY/FORGINGS',
    'GENERAL ENGINEERING - FABRICATION',
    'PHARMACEUTICALS',
    'DISTILLERIES/BREWERIES',
    'TRANSPORTATION',
    'AUTOMOBILE SERVICE STATIONS',
    'IRON AND STEEL',
    'GOVT TENDERS AND TENDERS',
    'RAILWAYS',
    'RESEARCH, TRAINING & EDUCATIONAL INSTITUTION',
    'CONSTRUCTION',
    'TELECOMMUNICATION',
    'POWER',
    'WW OEM DOMESTIC CUSTOMERS',
    'WW OE - INDIRECT EXPORT',
    'MINING',
    'GUNNETING, SHOT AND SAND BLASTING',
    'JACK HAMMER APPLICATION',
    'DRILLING OE\'S',
    'MANUFACTURER (AUTOMOBILES)',
    'GRANITE MINING',
    'BLUE METAL QUARRY',
    'FOOD PROCESSING INDUSTRY.',
    'SHIPYARD/PORT',
    'PUBLISHING/PRINTING/PRESS.',
    'GENERAL ENGINEERING - FILTER MANUFACTURING',
    'CABLES',
    'CHEMICAL',
    'GENERAL ENGINEERING - MOTOR AND PUMPS',
    'PAINT',
    'PLASTIC',
    'Other Industry',
    'GENERAL ENGINEERING - TOOLS,DIE & FIXTURE ITEMS',
    'GENERAL ENGINEERING - INDUSTRIAL VALVES',
    'DYE & FERTILIZER',
    'TANNING/LEATHER PROCESING',
    'AGRI,FOOD STUFF &RELATED TECH',
    'CANNING/BOTTLING/PACKAGING',
    'METALLURGY',
    'AUTO ANCILLARY',
    'RUBBER',
    'ELECTRONICS &ELECTRICAL',
    'HOSPITAL/HEALTH',
    'KNITTING AND DYING',
    'RICE AND DAL SORTING',
    'WW END CUSTOMER - 7½" & Above',
    'WW END CUSTOMER - 4½" & 6½"',
    'WW END CUSTOMER - 6½"',
    'WW END CUSTOMER - 4½"',
    'CEMENT',
    'OXYGEN GENERATORS',
    'Retreading Solutions',
    'POLYMER & SYNTHETICS INDUSTRY',
    'PET BOTTLING',
    'MEDICAL EQUIPMENT & BIOTECH ENGINEERING',
    'PETROLEUM & OIL INDUSTRIES',
    'GLASS, FIBRE AND CERAMICS',
    'GENERAL ENGINEERING - WOOD WORK',
    'ENTERTAINMENT GROUPS',
    'AERONAUTICS',
    'FMCG',
    'GENERAL ENGINEERING - WIND MILL FABRICATORS',
    'JEWELLERY',
    'SUGAR',
    'WW END CUSTOMER',
    'CB DRILLING',
    'DRILLING APPLICATION',
    'MARBLE MINING',
    'SPINNING - TEXTILES',
    'WEAVING- AIR JET LOOMS - TEXTILES',
    'OEM',
    'Consultant',
    'EPC',
    'Trader',
];

try {
    $obconn->beginTransaction();

    $obconn->exec('TRUNCATE TABLE industry_segments RESTART IDENTITY');

    $insert = $obconn->prepare("
        INSERT INTO industry_segments (name, status, created_by)
        VALUES (:name, 'active', 'system')
    ");

    foreach ($segments as $name) {
        $insert->bindValue(':name', $name);
        $insert->execute();
    }

    $obconn->commit();

    echo 'Industry segments replaced successfully. Inserted ' . count($segments) . " records.\n";
} catch (Throwable $e) {
    if ($obconn->inTransaction()) {
        $obconn->rollBack();
    }

    fwrite(STDERR, 'Failed: ' . $e->getMessage() . "\n");
    exit(1);
}
