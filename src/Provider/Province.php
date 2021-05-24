<?php namespace PA\ProvinceTh\Provider;


class Province extends ProviderCollection {


    /**
     * @return array
     */
    public function geography()
    {
        return $this->belongsTo(Geography::class);
    }


    /**
     * @return Amphure|ProviderCollection
     */
    public function amphures()
    {
        return $this->hasMany(Amphure::class);
    }


    /**
     * @return array
     */
    public function data()
    {
        return $provinces = [
            ['id' => '1', 'code' => '10', 'name_th' => 'กรุงเทพมหานคร', 'name_en' => 'Bangkok', 'geography_id' => '2'],
            ['id' => '2', 'code' => '11', 'name_th' => 'สมุทรปราการ', 'name_en' => 'Samut Prakan', 'geography_id' => '2'],
            ['id' => '3', 'code' => '12', 'name_th' => 'นนทบุรี', 'name_en' => 'Nonthaburi', 'geography_id' => '2'],
            ['id' => '4', 'code' => '13', 'name_th' => 'ปทุมธานี', 'name_en' => 'Pathum Thani', 'geography_id' => '2'],
            ['id' => '5', 'code' => '14', 'name_th' => 'พระนครศรีอยุธยา', 'name_en' => 'Phra Nakhon Si Ayutthaya', 'geography_id' => '2'],
            ['id' => '6', 'code' => '15', 'name_th' => 'อ่างทอง', 'name_en' => 'Ang Thong', 'geography_id' => '2'],
            ['id' => '7', 'code' => '16', 'name_th' => 'ลพบุรี', 'name_en' => 'Lopburi', 'geography_id' => '2'],
            ['id' => '8', 'code' => '17', 'name_th' => 'สิงห์บุรี', 'name_en' => 'Sing Buri', 'geography_id' => '2'],
            ['id' => '9', 'code' => '18', 'name_th' => 'ชัยนาท', 'name_en' => 'Chai Nat', 'geography_id' => '2'],
            ['id' => '10', 'code' => '19', 'name_th' => 'สระบุรี', 'name_en' => 'Saraburi', 'geography_id' => '2'],
            ['id' => '11', 'code' => '20', 'name_th' => 'ชลบุรี', 'name_en' => 'Chon Buri', 'geography_id' => '5'],
            ['id' => '12', 'code' => '21', 'name_th' => 'ระยอง', 'name_en' => 'Rayong', 'geography_id' => '5'],
            ['id' => '13', 'code' => '22', 'name_th' => 'จันทบุรี', 'name_en' => 'Chanthaburi', 'geography_id' => '5'],
            ['id' => '14', 'code' => '23', 'name_th' => 'ตราด', 'name_en' => 'Trat', 'geography_id' => '5'],
            ['id' => '15', 'code' => '24', 'name_th' => 'ฉะเชิงเทรา', 'name_en' => 'Chachoengsao', 'geography_id' => '5'],
            ['id' => '16', 'code' => '25', 'name_th' => 'ปราจีนบุรี', 'name_en' => 'Prachin Buri', 'geography_id' => '5'],
            ['id' => '17', 'code' => '26', 'name_th' => 'นครนายก', 'name_en' => 'Nakhon Nayok', 'geography_id' => '2'],
            ['id' => '18', 'code' => '27', 'name_th' => 'สระแก้ว', 'name_en' => 'Sa Kaeo', 'geography_id' => '5'],
            ['id' => '19', 'code' => '30', 'name_th' => 'นครราชสีมา', 'name_en' => 'Nakhon Ratchasima', 'geography_id' => '3'],
            ['id' => '20', 'code' => '31', 'name_th' => 'บุรีรัมย์', 'name_en' => 'Buri Ram', 'geography_id' => '3'],
            ['id' => '21', 'code' => '32', 'name_th' => 'สุรินทร์', 'name_en' => 'Surin', 'geography_id' => '3'],
            ['id' => '22', 'code' => '33', 'name_th' => 'ศรีสะเกษ', 'name_en' => 'Si Sa Ket', 'geography_id' => '3'],
            ['id' => '23', 'code' => '34', 'name_th' => 'อุบลราชธานี', 'name_en' => 'Ubon Ratchathani', 'geography_id' => '3'],
            ['id' => '24', 'code' => '35', 'name_th' => 'ยโสธร', 'name_en' => 'Yasothon', 'geography_id' => '3'],
            ['id' => '25', 'code' => '36', 'name_th' => 'ชัยภูมิ', 'name_en' => 'Chaiyaphum', 'geography_id' => '3'],
            ['id' => '26', 'code' => '37', 'name_th' => 'อำนาจเจริญ', 'name_en' => 'Amnat Charoen', 'geography_id' => '3'],
            ['id' => '27', 'code' => '39', 'name_th' => 'หนองบัวลำภู', 'name_en' => 'Nong Bua Lam Phu', 'geography_id' => '3'],
            ['id' => '28', 'code' => '40', 'name_th' => 'ขอนแก่น', 'name_en' => 'Khon Kaen', 'geography_id' => '3'],
            ['id' => '29', 'code' => '41', 'name_th' => 'อุดรธานี', 'name_en' => 'Udon Thani', 'geography_id' => '3'],
            ['id' => '30', 'code' => '42', 'name_th' => 'เลย', 'name_en' => 'Loei', 'geography_id' => '3'],
            ['id' => '31', 'code' => '43', 'name_th' => 'หนองคาย', 'name_en' => 'Nong Khai', 'geography_id' => '3'],
            ['id' => '32', 'code' => '44', 'name_th' => 'มหาสารคาม', 'name_en' => 'Maha Sarakham', 'geography_id' => '3'],
            ['id' => '33', 'code' => '45', 'name_th' => 'ร้อยเอ็ด', 'name_en' => 'Roi Et', 'geography_id' => '3'],
            ['id' => '34', 'code' => '46', 'name_th' => 'กาฬสินธุ์', 'name_en' => 'Kalasin', 'geography_id' => '3'],
            ['id' => '35', 'code' => '47', 'name_th' => 'สกลนคร', 'name_en' => 'Sakon Nakhon', 'geography_id' => '3'],
            ['id' => '36', 'code' => '48', 'name_th' => 'นครพนม', 'name_en' => 'Nakhon Phanom', 'geography_id' => '3'],
            ['id' => '37', 'code' => '49', 'name_th' => 'มุกดาหาร', 'name_en' => 'Mukdahan', 'geography_id' => '3'],
            ['id' => '38', 'code' => '50', 'name_th' => 'เชียงใหม่', 'name_en' => 'Chiang Mai', 'geography_id' => '1'],
            ['id' => '39', 'code' => '51', 'name_th' => 'ลำพูน', 'name_en' => 'Lamphun', 'geography_id' => '1'],
            ['id' => '40', 'code' => '52', 'name_th' => 'ลำปาง', 'name_en' => 'Lampang', 'geography_id' => '1'],
            ['id' => '41', 'code' => '53', 'name_th' => 'อุตรดิตถ์', 'name_en' => 'Uttaradit', 'geography_id' => '1'],
            ['id' => '42', 'code' => '54', 'name_th' => 'แพร่', 'name_en' => 'Phrae', 'geography_id' => '1'],
            ['id' => '43', 'code' => '55', 'name_th' => 'น่าน', 'name_en' => 'Nan', 'geography_id' => '1'],
            ['id' => '44', 'code' => '56', 'name_th' => 'พะเยา', 'name_en' => 'Phayao', 'geography_id' => '1'],
            ['id' => '45', 'code' => '57', 'name_th' => 'เชียงราย', 'name_en' => 'Chiang Rai', 'geography_id' => '1'],
            ['id' => '46', 'code' => '58', 'name_th' => 'แม่ฮ่องสอน', 'name_en' => 'Mae Hong Son', 'geography_id' => '1'],
            ['id' => '47', 'code' => '60', 'name_th' => 'นครสวรรค์', 'name_en' => 'Nakhon Sawan', 'geography_id' => '2'],
            ['id' => '48', 'code' => '61', 'name_th' => 'อุทัยธานี', 'name_en' => 'Uthai Thani', 'geography_id' => '2'],
            ['id' => '49', 'code' => '62', 'name_th' => 'กำแพงเพชร', 'name_en' => 'Kamphaeng Phet', 'geography_id' => '2'],
            ['id' => '50', 'code' => '63', 'name_th' => 'ตาก', 'name_en' => 'Tak', 'geography_id' => '4'],
            ['id' => '51', 'code' => '64', 'name_th' => 'สุโขทัย', 'name_en' => 'Sukhothai', 'geography_id' => '2'],
            ['id' => '52', 'code' => '65', 'name_th' => 'พิษณุโลก', 'name_en' => 'Phitsanulok', 'geography_id' => '2'],
            ['id' => '53', 'code' => '66', 'name_th' => 'พิจิตร', 'name_en' => 'Phichit', 'geography_id' => '2'],
            ['id' => '54', 'code' => '67', 'name_th' => 'เพชรบูรณ์', 'name_en' => 'Phetchabun', 'geography_id' => '2'],
            ['id' => '55', 'code' => '70', 'name_th' => 'ราชบุรี', 'name_en' => 'Ratchaburi', 'geography_id' => '4'],
            ['id' => '56', 'code' => '71', 'name_th' => 'กาญจนบุรี', 'name_en' => 'Kanchanaburi', 'geography_id' => '4'],
            ['id' => '57', 'code' => '72', 'name_th' => 'สุพรรณบุรี', 'name_en' => 'Suphan Buri', 'geography_id' => '2'],
            ['id' => '58', 'code' => '73', 'name_th' => 'นครปฐม', 'name_en' => 'Nakhon Pathom', 'geography_id' => '2'],
            ['id' => '59', 'code' => '74', 'name_th' => 'สมุทรสาคร', 'name_en' => 'Samut Sakhon', 'geography_id' => '2'],
            ['id' => '60', 'code' => '75', 'name_th' => 'สมุทรสงคราม', 'name_en' => 'Samut Songkhram', 'geography_id' => '2'],
            ['id' => '61', 'code' => '76', 'name_th' => 'เพชรบุรี', 'name_en' => 'Phetchaburi', 'geography_id' => '4'],
            ['id' => '62', 'code' => '77', 'name_th' => 'ประจวบคีรีขันธ์', 'name_en' => 'Prachuap Khiri Khan', 'geography_id' => '4'],
            ['id' => '63', 'code' => '80', 'name_th' => 'นครศรีธรรมราช', 'name_en' => 'Nakhon Si Thammarat', 'geography_id' => '6'],
            ['id' => '64', 'code' => '81', 'name_th' => 'กระบี่', 'name_en' => 'Krabi', 'geography_id' => '6'],
            ['id' => '65', 'code' => '82', 'name_th' => 'พังงา', 'name_en' => 'Phangnga', 'geography_id' => '6'],
            ['id' => '66', 'code' => '83', 'name_th' => 'ภูเก็ต', 'name_en' => 'Phuket', 'geography_id' => '6'],
            ['id' => '67', 'code' => '84', 'name_th' => 'สุราษฎร์ธานี', 'name_en' => 'SuratThani', 'geography_id' => '6'],
            ['id' => '68', 'code' => '85', 'name_th' => 'ระนอง', 'name_en' => 'Ranong', 'geography_id' => '6'],
            ['id' => '69', 'code' => '86', 'name_th' => 'ชุมพร', 'name_en' => 'Chumphon', 'geography_id' => '6'],
            ['id' => '70', 'code' => '90', 'name_th' => 'สงขลา', 'name_en' => 'Songkhla', 'geography_id' => '6'],
            ['id' => '71', 'code' => '91', 'name_th' => 'สตูล', 'name_en' => 'Satun', 'geography_id' => '6'],
            ['id' => '72', 'code' => '92', 'name_th' => 'ตรัง', 'name_en' => 'Trang', 'geography_id' => '6'],
            ['id' => '73', 'code' => '93', 'name_th' => 'พัทลุง', 'name_en' => 'Phatthalung', 'geography_id' => '6'],
            ['id' => '74', 'code' => '94', 'name_th' => 'ปัตตานี', 'name_en' => 'Pattani', 'geography_id' => '6'],
            ['id' => '75', 'code' => '95', 'name_th' => 'ยะลา', 'name_en' => 'Yala', 'geography_id' => '6'],
            ['id' => '76', 'code' => '96', 'name_th' => 'นราธิวาส', 'name_en' => 'Narathiwat', 'geography_id' => '6'],
            ['id' => '77', 'code' => '97', 'name_th' => 'บึงกาฬ', 'name_en' => 'Bueng Kan', 'geography_id' => '3'],
        ];
    }
}
