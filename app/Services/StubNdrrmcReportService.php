<?php

namespace App\Services;

use App\Contracts\NdrrmcReportServiceInterface;
use Illuminate\Support\Facades\Log;
use SimpleXMLElement;

class StubNdrrmcReportService implements NdrrmcReportServiceInterface
{
    /**
     * Submit a Situation Report (SitRep) to NDRRMC.
     *
     * Builds well-formed XML following Philippine disaster reporting conventions
     * with fields modeled from NDRRMC SitRep format.
     *
     * @param  array{incident_no: string, incident_type: string, priority: string, location_text: string, barangay: string, coordinates: array{lat: float, lng: float}|null, outcome: string, units_deployed: int, timeline_summary: string, created_at: string, resolved_at: string|null}  $incidentData
     * @return array{status: string, reference_id: string, xml_payload: string}
     */
    public function submitSitRep(array $incidentData): array
    {
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><SituationReport/>');

        $reportNumber = 'SITREP-'.date('Y').'-'.str_pad(
            (string) abs(crc32($incidentData['incident_no']) % 99999),
            5,
            '0',
            STR_PAD_LEFT,
        );

        $xml->addChild('report_number', $reportNumber);
        $xml->addChild('date_time', $incidentData['created_at']);
        $xml->addChild('type_of_incident', $incidentData['incident_type']);

        $location = $xml->addChild('location');
        $location->addChild('barangay', $incidentData['barangay']);
        $location->addChild('municipality', 'Butuan City');
        $location->addChild('province', 'Agusan del Norte');
        $location->addChild('region', 'Caraga (Region XIII)');

        if ($incidentData['coordinates'] !== null) {
            $coords = $location->addChild('coordinates');
            $coords->addChild('latitude', (string) $incidentData['coordinates']['lat']);
            $coords->addChild('longitude', (string) $incidentData['coordinates']['lng']);
        }

        $xml->addChild('affected_persons', 'Under assessment');

        $casualties = $xml->addChild('casualties');
        $casualties->addChild('dead', '0');
        $casualties->addChild('injured', 'Under assessment');
        $casualties->addChild('missing', '0');

        $xml->addChild('response_actions', $incidentData['timeline_summary']);
        $xml->addChild('resources_deployed', (string) $incidentData['units_deployed']);

        $statusOfOperations = $incidentData['resolved_at'] !== null ? 'Resolved' : 'Ongoing';
        $xml->addChild('status_of_operations', $statusOfOperations);

        $xmlString = $xml->asXML();

        Log::info('StubNdrrmcReportService::submitSitRep', [
            'incident_no' => $incidentData['incident_no'],
            'xml_length' => strlen($xmlString),
        ]);

        $referenceId = 'SITREP-STUB-'.strtoupper(substr(md5($incidentData['incident_no']), 0, 8));

        return [
            'status' => 'submitted',
            'reference_id' => $referenceId,
            'xml_payload' => $xmlString,
        ];
    }
}
