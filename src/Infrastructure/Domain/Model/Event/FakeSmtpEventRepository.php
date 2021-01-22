<?php
declare(strict_types=1);

namespace ZoiloMora\HikvisionCommunicator\Infrastructure\Domain\Model\Event;

use GuzzleHttp\Client;
use ZoiloMora\HikvisionCommunicator\Domain\Model\Event\Aggregate\Camera;
use ZoiloMora\HikvisionCommunicator\Domain\Model\Event\Aggregate\Device;
use ZoiloMora\HikvisionCommunicator\Domain\Model\Event\Event;
use ZoiloMora\HikvisionCommunicator\Domain\Model\Event\EventRepository;

final class FakeSmtpEventRepository implements EventRepository
{
    private Client $client;
    private \DateTimeZone $dateTimeZone;

    public function __construct(Client $client, string $timezone)
    {
        $this->client = $client;
        $this->dateTimeZone = new \DateTimeZone($timezone);
    }

    public function getAll(): array
    {
        $response = $this->client->get('/api/emails');
        $raw = $response->getBody()->getContents();

        $objects = \json_decode($raw, true);

        $events = [];
        foreach ($objects as $object) {
            $text = $object['text'];
            if (false === \strpos($text, 'EVENT TYPE')) {
                continue;
            }

            $events[] = $this->mapBody($text);
        }

        return $events;
    }

    public function deleteAll(): void
    {
        $this->client->delete('/api/emails');
    }

    private function mapBody(string $body): Event
    {
        return Event::from(
            $this->mapType($body),
            Device::from(
                $this->mapVrName($body),
                $this->mapVrSerialNumber($body),
            ),
            Camera::from(
                $this->mapCameraName($body),
                $this->mapCameraNumber($body),
            ),
            $this->mapOccurredOn($body),
        );
    }

    private function mapType(string $body): string
    {
        preg_match_all('/EVENT TYPE:[ ]*(.*)/', $body, $output_array);

        return \trim($output_array[1][0]);
    }

    private function mapOccurredOn(string $body): \DateTime
    {
        preg_match_all('/EVENT TIME:[ ]*(.*)/', $body, $output_array);
        $text = \trim($output_array[1][0]);

        return \DateTime::createFromFormat('Y-m-d,H:i:s', $text, $this->dateTimeZone);
    }

    private function mapVrName(string $body): string
    {
        preg_match_all('/VR NAME:[ ]*(.*)/', $body, $output_array);

        return \trim($output_array[1][0]);
    }

    private function mapVrSerialNumber(string $body): string
    {
        preg_match_all('/VR S\/N:[ ]*(.*)/', $body, $output_array);

        return \trim($output_array[1][0]);
    }

    private function mapCameraName(string $body): string
    {
        preg_match_all('/CAMERA NAME\(NUM\):[ ]*(.*)\((.*)\)/', $body, $output_array);

        return \trim($output_array[1][0]);
    }

    private function mapCameraNumber(string $body): string
    {
        preg_match_all('/CAMERA NAME\(NUM\):[ ]*(.*)\((.*)\)/', $body, $output_array);

        return \trim($output_array[2][0]);
    }
}
