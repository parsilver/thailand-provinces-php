<?php

declare(strict_types=1);

namespace Farzai\ThailandAddress\Service;

use Farzai\ThailandAddress\Data\Province;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class ProvinceScraperService
{
    private const string DATA_URL = 'https://data.go.th/dataset/gis-01';

    private const string PATTERN = 'ข้อมูลที่ตั้งและสภาพทั่วไปของหมู่บ้าน';

    private const int MAX_RETRIES = 3;

    private const array BACKOFF_DELAYS = [1, 2, 4]; // seconds

    private HttpClientInterface $httpClient;

    public function __construct(?HttpClientInterface $httpClient = null)
    {
        $this->httpClient = $httpClient ?? HttpClient::create([
            'timeout' => 30,
            'max_redirects' => 5,
        ]);
    }

    /**
     * Scrape all provinces from the Thai government data portal.
     *
     * @return list<Province>
     *
     * @throws \RuntimeException if scraping fails after all retries
     */
    public function scrapeProvinces(): array
    {
        $html = $this->fetchHtmlWithRetry();
        $crawler = new Crawler($html);

        return $this->extractProvinces($crawler);
    }

    /**
     * Fetch HTML content with retry logic and exponential backoff.
     *
     * @throws \RuntimeException if all retries fail
     */
    private function fetchHtmlWithRetry(): string
    {
        $lastException = null;

        for ($attempt = 0; $attempt < self::MAX_RETRIES; $attempt++) {
            try {
                $response = $this->httpClient->request('GET', self::DATA_URL);

                return $response->getContent();
            } catch (TransportExceptionInterface|\Exception $e) {
                $lastException = $e;

                if ($attempt < self::MAX_RETRIES - 1) {
                    $delay = self::BACKOFF_DELAYS[$attempt];
                    sleep($delay);
                }
            }
        }

        throw new \RuntimeException(
            sprintf('Failed to fetch data after %d attempts: %s', self::MAX_RETRIES, $lastException?->getMessage() ?? 'Unknown error'),
            0,
            $lastException
        );
    }

    /**
     * Extract province information from the HTML crawler.
     *
     * @return list<Province>
     */
    private function extractProvinces(Crawler $crawler): array
    {
        $provinces = [];

        // Find all anchor tags containing the pattern
        $crawler->filter('a')->each(function (Crawler $node) use (&$provinces): void {
            $text = $node->text();

            // Check if the text contains our pattern
            if (! str_contains($text, self::PATTERN)) {
                return;
            }

            $id = $node->attr('id');
            if ($id === null || $id === '') {
                return;
            }

            // Extract province name from the text
            // Pattern: "ข้อมูลที่ตั้งและสภาพทั่วไปของหมู่บ้าน {province name}"
            $provinceName = str_replace(self::PATTERN, '', $text);
            $provinceName = trim($provinceName);

            if ($provinceName === '' || str_contains($provinceName, 'data_dictionary')) {
                return;
            }

            // Clean up: remove "จังหวัด" prefix and download counts
            $provinceName = preg_replace('/^จังหวัด/', '', $provinceName) ?? $provinceName;
            $provinceName = preg_replace('/\s+\d+\s+downloads?$/', '', $provinceName) ?? $provinceName;
            $provinceName = trim($provinceName);

            if ($provinceName === '') {
                return;
            }

            // Create English slug from Thai name (we'll use transliteration for filename)
            $englishName = $this->transliterateThaiToEnglish($provinceName);

            $provinces[] = new Province(
                name: $englishName,
                thaiName: $provinceName,
                resourceId: $id,
            );
        });

        return $provinces;
    }

    /**
     * Simple transliteration from Thai to English (basic version).
     * In production, you might want to use a proper Thai transliteration library.
     */
    private function transliterateThaiToEnglish(string $thai): string
    {
        // Map of Thai province names to their English equivalents
        $provinceMap = [
            'กระบี่' => 'Krabi',
            'กรุงเทพมหานคร' => 'Bangkok',
            'กาญจนบุรี' => 'Kanchanaburi',
            'กาฬสินธุ์' => 'Kalasin',
            'กำแพงเพชร' => 'Kamphaeng Phet',
            'ขอนแก่น' => 'Khon Kaen',
            'จันทบุรี' => 'Chanthaburi',
            'ฉะเชิงเทรา' => 'Chachoengsao',
            'ชลบุรี' => 'Chonburi',
            'ชัยนาท' => 'Chai Nat',
            'ชัยภูมิ' => 'Chaiyaphum',
            'ชุมพร' => 'Chumphon',
            'เชียงราย' => 'Chiang Rai',
            'เชียงใหม่' => 'Chiang Mai',
            'ตรัง' => 'Trang',
            'ตราด' => 'Trat',
            'ตาก' => 'Tak',
            'นครนายก' => 'Nakhon Nayok',
            'นครปฐม' => 'Nakhon Pathom',
            'นครพนม' => 'Nakhon Phanom',
            'นครราชสีมา' => 'Nakhon Ratchasima',
            'นครศรีธรรมราช' => 'Nakhon Si Thammarat',
            'นครสวรรค์' => 'Nakhon Sawan',
            'นนทบุรี' => 'Nonthaburi',
            'นราธิวาส' => 'Narathiwat',
            'น่าน' => 'Nan',
            'บึงกาฬ' => 'Bueng Kan',
            'บุรีรัมย์' => 'Buri Ram',
            'ปทุมธานี' => 'Pathum Thani',
            'ประจวบคีรีขันธ์' => 'Prachuap Khiri Khan',
            'ปราจีนบุรี' => 'Prachin Buri',
            'ปัตตานี' => 'Pattani',
            'พระนครศรีอยุธยา' => 'Phra Nakhon Si Ayutthaya',
            'พังงา' => 'Phang Nga',
            'พัทลุง' => 'Phatthalung',
            'พิจิตร' => 'Phichit',
            'พิษณุโลก' => 'Phitsanulok',
            'เพชรบุรี' => 'Phetchaburi',
            'เพชรบูรณ์' => 'Phetchabun',
            'แพร่' => 'Phrae',
            'ภูเก็ต' => 'Phuket',
            'มหาสารคาม' => 'Maha Sarakham',
            'มุกดาหาร' => 'Mukdahan',
            'แม่ฮ่องสอน' => 'Mae Hong Son',
            'ยโสธร' => 'Yasothon',
            'ยะลา' => 'Yala',
            'ร้อยเอ็ด' => 'Roi Et',
            'ระนอง' => 'Ranong',
            'ระยอง' => 'Rayong',
            'ราชบุรี' => 'Ratchaburi',
            'ลพบุรี' => 'Lop Buri',
            'ลำปาง' => 'Lampang',
            'ลำพูน' => 'Lamphun',
            'เลย' => 'Loei',
            'ศรีสะเกษ' => 'Si Sa Ket',
            'สกลนคร' => 'Sakon Nakhon',
            'สงขลา' => 'Songkhla',
            'สตูล' => 'Satun',
            'สมุทรปราการ' => 'Samut Prakan',
            'สมุทรสงคราม' => 'Samut Songkhram',
            'สมุทรสาคร' => 'Samut Sakhon',
            'สระแก้ว' => 'Sa Kaeo',
            'สระบุรี' => 'Saraburi',
            'สิงห์บุรี' => 'Sing Buri',
            'สุโขทัย' => 'Sukhothai',
            'สุพรรณบุรี' => 'Suphan Buri',
            'สุราษฎร์ธานี' => 'Surat Thani',
            'สุรินทร์' => 'Surin',
            'หนองคาย' => 'Nong Khai',
            'หนองบัวลำภู' => 'Nong Bua Lam Phu',
            'อ่างทอง' => 'Ang Thong',
            'อำนาจเจริญ' => 'Amnat Charoen',
            'อุดรธานี' => 'Udon Thani',
            'อุตรดิตถ์' => 'Uttaradit',
            'อุทัยธานี' => 'Uthai Thani',
            'อุบลราชธานี' => 'Ubon Ratchathani',
        ];

        return $provinceMap[$thai] ?? $thai;
    }
}
