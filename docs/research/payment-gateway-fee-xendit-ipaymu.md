# Payment gateway: biaya Xendit & iPaymu, PPN, larangan surcharge, API

Pertanyaan: berapa biaya transaksi Xendit dan iPaymu per kanal (2026), termasuk/tidak termasuk PPN; bagaimana `fees` di Xendit Invoice API dan field callback-nya; apakah biaya boleh dibebankan ke pelanggan; bagaimana API iPaymu v2 (redirect, direct, signature, callback, cek transaksi); dan apakah biaya Xendit yang sebenarnya bisa diambil lewat API.

Diriset 2026-10-03. Setiap klaim menyebut sumber dan cara membacanya. Yang tidak bisa dicek langsung ditandai **TIDAK TERVERIFIKASI**.

**Batasan sumber:** halaman harga Xendit (`xendit.co/en/pricing/`, `/id/biaya/`, `/en-id/pricing/`) diblokir Cloudflare (HTTP 403) untuk WebFetch, curl, dan proxy pembaca. Angka harga Xendit **saat ini** diambil dari ekstrak indeks mesin pencari atas halaman resmi itu (dibatasi ke domain `xendit.co`), jadi isinya halaman resmi tapi dibaca secara tidak langsung. Angka harga Xendit **lama** dibaca langsung dari snapshot Wayback Machine halaman resmi. Halaman bantuan (help.xendit.co lewat Zendesk JSON API) dan docs.xendit.co dibaca langsung.

## Ringkasan

| Hal | Jawaban |
| --- | --- |
| VA Xendit "Rp 9.000" di dashboard | Cocok dengan **payment method fee VA baru Rp 9.000** (harga direvisi mulai 1 Agu 2026). Mulai 1 Okt 2026 ada tambahan **Xendit Processing Fee Rp 4.000** per transaksi. Keduanya belum termasuk PPN. Rp 4.000 adalah harga lama. |
| Total VA Xendit per transaksi (mulai 1 Okt 2026) | (9.000 + 4.000) × 1,11 = **Rp 14.430** (asumsi processing fee juga dikenai PPN) |
| PPN atas biaya Xendit | 12% × DPP 11/12 = efektif **11%** di atas fee, kecuali QRIS (sudah termasuk PPN) |
| `fees` di invoice Xendit | Satu daftar biaya tetap untuk seluruh invoice, **tidak bisa dibedakan per metode bayar**. Xendit sendiri menulis bahwa beberapa negara melarang pembebanan biaya transaksi ke pelanggan, "e.g. Indonesia". |
| Surcharge ke pelanggan | **Dilarang**: PBI 23/6/PBI/2021 Pasal 52 ayat (1), untuk semua biaya PJP, bukan hanya MDR QRIS. Ditegaskan lagi di PBI 10/2025 (Penjelasan Pasal 61 huruf a). Untuk QRIS, BI menulis bahwa MDR "tidak boleh dibebankan kepada konsumen". |
| MDR QRIS 0% | Berlaku mulai 1 Okt 2026 untuk semua merchant pada transaksi ≤ Rp 100.000 (merchant mikro ≤ Rp 500.000). Belum terverifikasi apakah Xendit/iPaymu sudah menerapkannya. |
| iPaymu VA | Rp 3.500–4.500 (diskon dari harga coret Rp 5.000). Status PPN **TIDAK TERVERIFIKASI**, halaman harga tidak menyebut PPN/pajak. |
| Biaya Xendit lewat API | Tidak ada API daftar harga. Biaya **aktual** per transaksi ada di `GET /transactions` → `fee.xendit_fee` + `fee.value_added_tax`, final saat `fee.status = COMPLETED`. Callback invoice **tidak** membawa biaya Xendit (`fees_paid_amount` sudah deprecated sejak 18 Mei 2022). |
| Biaya legacy API | Mulai 1 Okt 2026, Xendit mengenakan **Monthly Maintenance Fee USD 250 (Rp 4.500.000)** jika masih memakai legacy API. `/v2/invoices` disebut Xendit sebagai "legacy Payment Link". |

## 1. Biaya transaksi Xendit Indonesia

### 1a. Struktur biaya dan kebijakan baru 2026
- "For every transaction, we charge a fixed processing fee + a payment method fee or a payout fee." Dibaca langsung: [What are the pricing for Xendit products?](https://help.xendit.co/hc/en-us/articles/360039086452-What-are-the-pricing-for-Xendit-products) (diperbarui 2026-09-10).
- Xendit Pricing Policy, dibaca langsung: [Xendit Pricing Policy](https://help.xendit.co/hc/en-us/articles/59516240127129-Xendit-Pricing-Policy) (diperbarui 2026-09-21):
  - "Revised transaction fees across all payment methods" berlaku **1 Agu 2026**.
  - **Xendit Processing Fee** "introduced to each transaction" mulai **1 Okt 2026**, untuk "all attempts of payment acceptance, payout and refunds", **termasuk percobaan yang gagal**.
  - Refund dikenai processing fee lagi. Payment method fee dan processing fee transaksi asal tidak dikembalikan.
  - **Monthly Maintenance Fee USD 250 (IDR 4.500.000)** jika masih memakai legacy API, mulai 1 Okt 2026. Tautan "legacy API endpoints" mengarah ke `archive.developers.xendit.co/api-reference/`, tempat Create Invoice `POST /v2/invoices` didokumentasikan. Cara cek: transaksi lewat Payments API baru memakai prefix Payment ID `py-`.
  - **Monthly Minimum Fee USD 50 (IDR 900.000)** jika total invoice bulanan < USD 50 atau akun dorman.
  - Chargeback LPM USD 15 (IDR 270.000), kartu USD 25 (IDR 450.000).
- Processing fee "accumulated and deducted daily, in a single lump sum" dan dirinci per transaksi di Billing report. Dibaca langsung: [Transaction fees](https://docs.xendit.co/docs/transaction-fees.md).
- Migrasi: dokumen Xendit menyebut `/v2/invoices` sebagai "legacy Payment Link (previously called Invoice)" dan penggantinya `POST /sessions` dengan `mode: PAYMENT_LINK`; `fees` menjadi item `type: FEE` di dalam `items`. Dibaca langsung: [Migrate from (legacy) Payment Links/Invoice to Payment Session](https://docs.xendit.co/docs/migrate-to-payment-session.md).

### 1b. Harga per kanal saat ini (setelah revisi 1 Agu 2026)
Sumber: ekstrak indeks pencarian atas [xendit.co/en/pricing](https://www.xendit.co/en/pricing/) dan [xendit.co/en-id/pricing](https://www.xendit.co/en-id/pricing/) karena halaman langsung diblokir 403. Statusnya **sebagian terverifikasi**: sumbernya resmi, tetapi dibaca tidak langsung. Cocokkan dengan menu Billing di dashboard Xendit.

| Kanal | Payment method fee | Processing fee (mulai 1 Okt 2026) | PPN |
| --- | --- | --- | --- |
| VA (BCA, BNI, BRI, BSI, Mandiri, Muamalat, Neo Commerce/BNC, Sahabat Sampoerna, CIMB, Permata) | Rp 9.000 | Rp 4.000 | belum termasuk |
| Alfamart | Rp 9.000 | Rp 4.000 | belum termasuk |
| Indomaret | Rp 9.000 | Rp 4.000 | belum termasuk |
| QRIS | 0,70% | Rp 4.000 | 0,70% **sudah termasuk PPN** |
| DANA | 3,00% | Rp 4.000 | belum termasuk |
| OVO | 5,50% (ekstrak lain: 3,00% untuk merchant non-digital) | Rp 4.000 | belum termasuk |
| ShopeePay | 3,60% | Rp 4.000 | **TIDAK TERVERIFIKASI** (halaman lama menyebut ShopeePay sudah termasuk pajak) |
| LinkAja | 2,00% | Rp 4.000 | belum termasuk |
| Kartu kredit (Visa/MC/JCB) | 2,90% + Rp 2.000 | Rp 4.000 | belum termasuk |

- Tarif kartu 2,9% + Rp 2.000 (aggregator) juga dibaca langsung di [How much the fee for Card payment method?](https://help.xendit.co/hc/en-us/articles/4418966204441-How-much-the-fee-for-Card-payment-method) (2026-08-11).
- Minimum fee QRIS: **TIDAK TERVERIFIKASI**, tidak ada di sumber mana pun yang terbaca.
- Apakah processing fee juga dikenai PPN: **TIDAK TERVERIFIKASI** secara eksplisit. Dokumen VAT menyebut "fees are generally VAT-exclusive" untuk semua layanan, jadi perhitungan di bawah mengasumsikan processing fee juga dikenai PPN.
- Apakah Xendit sudah menerapkan MDR QRIS 0% dari BI (lihat bagian 3) pada fee 0,7%-nya: **TIDAK TERVERIFIKASI**.

### 1c. Harga lama (sebelum 1 Agu 2026), sebagai pembanding
Dibaca langsung dari snapshot Wayback 2025-11-03 halaman resmi: [web.archive.org/…/xendit.co/id/biaya/](http://web.archive.org/web/20251103043044/https://www.xendit.co/id/biaya/). Isinya: "Semua biaya transaksi belum termasuk pajak yang berlaku di Indonesia, kecuali QRIS dan ShopeePay."
- VA aggregator Rp 4.000; VA switcher Rp 2.000 + biaya bank (sama dengan [Aggregator vs Switcher](https://help.xendit.co/hc/en-us/articles/31902742050201-What-is-the-difference-between-Aggregator-and-Switcher-Model-for-VA): "Fee per transaction (+VAT) 4.000 IDR").
- Alfamart Rp 5.000, Indomaret Rp 5.500.
- QRIS 0,7% (sudah termasuk pajak).
- OVO 1,50–3,18%, ShopeePay 2–4% (sudah termasuk pajak), LinkAja 1,50–3,15%, DANA 1,5% (dengan PIN) / 3% (tanpa PIN, termasuk recurring), AstraPay 1,5%, JeniusPay 2%.
- Kartu 2,90% + Rp 2.000 (AMEX 3,9% + Rp 2.000).
- Payment Link "FREE".

**Kesimpulan untuk Rp 9.000:** Rp 9.000 di dashboard sama dengan payment method fee VA baru sejak 1 Agu 2026, sebelum PPN dan sebelum processing fee. Rp 9.000 itu bukan Rp 4.000 + sesuatu. Tidak ditemukan perbedaan tarif antara VA langsung dan VA lewat Invoice/Payment Link; daftar harga baru menempatkan semua bank VA aggregator pada Rp 9.000.

### 1d. PPN atas biaya Xendit
- docs.xendit.co, dibaca langsung: [Value Added Tax (VAT)](https://docs.xendit.co/docs/value-added-tax-vat.md) (diperbarui 7 Jul 2026). Kutipan: "fees are generally VAT-exclusive"; "Indonesia VAT Rate: 12% (The subtotal is multiplied by 11/12, bringing effective VAT rate to 11%)"; contoh "5000 + 11/12 × 5000 × 12% = IDR 5,550". PPN "directly deducted from the transaction at settlement".
- Help center: "11% on top of the fees" ([How is VAT applied](https://help.xendit.co/hc/en-us/articles/360025721371-How-is-VAT-applied-in-the-charge), [Does Xendit apply VAT?](https://help.xendit.co/hc/en-us/articles/360038195131-Does-Xendit-apply-VAT)). Xendit adalah PKP dan menerbitkan Faktur Pajak. Biaya Xendit juga dikenai PPh 23 2% yang dipotong merchant lalu di-reimburse oleh Xendit.
- Dasar hukum: PMK 131/2024, berlaku 1 Jan 2025. PPN atas JKP = 12% × DPP nilai lain 11/12 = efektif 11%. Dibaca langsung: [pajak.go.id – PMK 131/2024](https://www.pajak.go.id/en/node/113453); naskah: [JDIH Kemenkeu PMK 131/2024](https://jdih.kemenkeu.go.id/dok/pmk-131-tahun-2024/view).
- **Yang menanggung PPN adalah merchant.** PPN dikenakan atas jasa Xendit ke merchant dan dipotong dari settlement.

### 1e. Biaya final termasuk PPN 11% (mulai 1 Okt 2026, harga di 1b)
Rumus: (fee kanal + processing Rp 4.000) × 1,11, kecuali QRIS yang 0,7%-nya sudah termasuk PPN. Contoh tagihan Rp 150.000:

| Kanal | Perhitungan | Total biaya |
| --- | --- | --- |
| VA | (9.000 + 4.000) × 1,11 | **Rp 14.430** (Rp 9.990 tanpa processing fee) |
| Alfamart / Indomaret | (9.000 + 4.000) × 1,11 | **Rp 14.430** |
| QRIS | 0,7% × 150.000 + 4.000 × 1,11 = 1.050 + 4.440 | **Rp 5.490** (jika MDR 0% BI diteruskan untuk ≤ Rp 100.000: hanya Rp 4.440, TIDAK TERVERIFIKASI) |
| DANA 3% | (4.500 + 4.000) × 1,11 | **Rp 9.435** |
| OVO 5,5% | (8.250 + 4.000) × 1,11 | **Rp 13.597,50** |
| ShopeePay 3,6% | (5.400 + 4.000) × 1,11 | **Rp 10.434** (jika 3,6% belum termasuk PPN) |
| LinkAja 2% | (3.000 + 4.000) × 1,11 | **Rp 7.770** |
| Kartu 2,9% + 2.000 | (4.350 + 2.000 + 4.000) × 1,11 | **Rp 11.488,50** |

Pembanding harga lama (tanpa processing fee): VA Rp 4.440, Alfamart Rp 5.550, Indomaret Rp 6.105.

## 2. Xendit Invoice API (`POST /v2/invoices`, legacy)
Sumber: dibaca langsung dari [archive.developers.xendit.co/api-reference](https://archive.developers.xendit.co/api-reference/), bagian Create Invoice dan Invoice Callback.

### 2a. `fees`
- "`fees` optional array — Array of items JSON objects describing the fee(s) that you charge to your end customer. This can be an admin fee, logistics fee, etc. This amount will be included in the total invoice amount and will be transferred to your balance when the transaction settles. Max array size: 10."
- Item `fees`: `type` (string, bebas, misalnya "ADMIN") dan `value` (number, boleh positif atau negatif).
- Peringatan resmi di parameter `value`: "Xendit recommends merchants to comply with local regulations when using this parameter to pass on transaction fees. **Certain countries do not allow that (e.g. Indonesia)**."
- `fees` adalah nominal tetap per invoice. Parameter ini tidak punya kondisi per metode atau kanal. Dokumen tidak menyebut cara lain untuk membuat biaya yang berbeda per metode bayar dalam satu hosted invoice. Satu-satunya cara adalah membuat invoice terpisah per kanal (`payment_methods: ["BCA"]`, dan seterusnya), tetapi itu tetap surcharge (lihat bagian 3).
- Help center: "No, xendit will charge the fee to you as a merchant… if you want to put the fee as part of the total amount… Set your own configuration on your web/app". Dibaca langsung: [Are we able to charge the xendit fee to the end customer?](https://help.xendit.co/hc/en-us/articles/33586413086233-Are-we-able-to-charge-the-xendit-fee-to-the-end-customer). Tidak ditemukan fitur dashboard "customer pays fees".

### 2b. `payment_methods`
- "Specify the payment channels that you wish to be available on your Invoice / Checkout UI. Leave this field empty if you would like all payment channels… or… defaults set in your Xendit Dashboard Settings." Nilai untuk Indonesia: `CREDIT_CARD, BCA, BNI, BSI, BRI, MANDIRI, PERMATA, SAHABAT_SAMPOERNA, BNC, ALFAMART, INDOMARET, OVO, DANA, SHOPEEPAY, LINKAJA, JENIUSPAY, DD_BRI, DD_BCA_KLIKPAY, KREDIVO, AKULAKU, ATOME, QRIS`.

### 2c. Field callback invoice
- Header `x-callback-token`: dicocokkan dengan Verification Token di Webhook Settings. Header `webhook-id` dipakai untuk idempotensi. Callback harus dibalas 200 dalam 30 detik; retry dilakukan selama 24 jam.
- `status`: `PAID` / `EXPIRED`. `amount`. `paid_amount` ("Total amount paid for the invoice"). `paid_at`.
- `payment_method`: `BANK_TRANSFER, CREDIT_CARD, RETAIL_OUTLET, EWALLET, DIRECT_DEBIT, PAYLATER, QR_CODE`.
- `payment_channel` (misalnya `BCA`, `MANDIRI`, `ALFAMART`, `OVO`, `QRIS`). `payment_destination` berisi nomor VA atau kode ritel.
- `bank_code` (hanya BANK_TRANSFER), `ewallet_type` (hanya EWALLET), `payment_id` (eWallet/PayLater/QR), `payment_details.receipt_id` / `source` (QRIS).
- `fees`: array yang dikirim saat create, yaitu biaya yang dibebankan ke pelanggan.
- `fees_paid_amount`: "**Xendit fee** paid from this transaction". `adjusted_received_amount`: "Amount attributable to you net of our fees". Keduanya "only if payment_channel is non switching Bank Partner and all of Retail Outlet" dan **"DEPRECATED SINCE 18th MAY 2022. If you need this information go to Xendit Dashboard > Balance Tab"**. Artinya `fees_paid_amount` adalah biaya **Xendit**, bukan biaya yang dibayar pelanggan, dan tidak bisa diandalkan.

## 3. Regulasi: surcharge biaya ke pelanggan
- **PBI No. 23/6/PBI/2021** tentang Penyedia Jasa Pembayaran, **Pasal 52**: "(1) Penyedia Barang dan/atau Jasa dilarang mengenakan biaya tambahan (surcharge) kepada Pengguna Jasa atas biaya yang dikenakan oleh PJP kepada Penyedia Barang dan/atau Jasa. (2) PJP wajib memastikan kepatuhan…". Dibaca langsung dari PDF: [PBI_230621.pdf](https://www.bi.go.id/id/publikasi/peraturan/Documents/PBI_230621.pdf). Larangan ini berlaku untuk **semua** biaya PJP (VA, e-wallet, ritel, kartu, QRIS), bukan hanya MDR QRIS.
- **PBI No. 10 Tahun 2025** tentang Pengaturan Industri Sistem Pembayaran (ditetapkan 24 Des 2025, berlaku 31 Mar 2026). Pasal 61 huruf a mewajibkan PSP menghentikan kerja sama jika merchant melakukan "tindakan yang merugikan". Penjelasan Pasal 61 huruf a angka 3 memberi contoh: "mengenakan biaya tambahan (surcharge) kepada Pengguna Jasa atas biaya yang seharusnya dikenakan oleh PJP kepada Penyedia Barang dan/atau Jasa". Pasal 184 menyatakan PBI 23/6/PBI/2021 "masih tetap berlaku sepanjang tidak bertentangan". Dibaca langsung: [PBI-102025.pdf](https://www.bi.go.id/id/publikasi/peraturan/Documents/PBI-102025.pdf).
- Khusus QRIS, BI menulis: "biaya MDR ini ditanggung oleh merchant dan tidak boleh dibebankan kepada konsumen". Tarif berlaku sejak 15 Mar 2025: mikro ≤ Rp 500 rb 0%, mikro > Rp 500 rb 0,3%, usaha kecil/menengah/besar 0,7%. Dibaca langsung: [MDR QRIS Bagi Merchant](https://www.bi.go.id/id/publikasi/ruang-media/cerita-bi/Pages/mdr-qris.aspx).
- **PADG 21/18/PADG/2019** (Implementasi QRIS) sendiri **tidak** memuat pasal larangan surcharge. Pasal 9 hanya menyebut skema biaya ditetapkan BI ("Contoh skema biaya yaitu merchant discount rate"). Dibaca langsung: [padg_211819.pdf](https://www.bi.go.id/id/publikasi/peraturan/Documents/padg_211819.pdf). Dasar larangannya adalah PBI di atas.
- **MDR QRIS 0% diperluas mulai 1 Okt 2026**: 0% untuk merchant mikro sampai Rp 500.000, dan untuk semua merchant lain (kecil, menengah, besar) pada transaksi sampai Rp 100.000. Siaran Pers No. 28/159/DKom, 17 Agu 2026. Dibaca langsung: [BI news release](https://www.bi.go.id/en/publikasi/ruang-media/news-release/Pages/sp_2815926.aspx).
- **Implikasi untuk UNMS:** `fees` di Xendit atau `feeDirection=BUYER` di iPaymu yang meneruskan biaya PJP ke pelanggan bertentangan dengan PBI 23/6/PBI/2021 Pasal 52. Yang aman adalah memasukkan biaya ke harga paket (harga sama untuk semua metode bayar), bukan menambahkan baris biaya sesuai metode bayar. Ini interpretasi, bukan nasihat hukum.

## 4. iPaymu

### 4a. Biaya per kanal
Dibaca langsung: [ipaymu.com/en/pricing](https://ipaymu.com/en/pricing/) dan [ipaymu.com/id/pricing](https://ipaymu.com/id/pricing/) (angkanya sama). "No activation or monthly fees."

| Kanal | Pencairan | Biaya |
| --- | --- | --- |
| VA AGI(BAG), CIMB, BNI, BRI, BMI, Permata, BTN, Danamon | H+0 | Rp 3.500 (harga coret Rp 5.000) |
| VA Mandiri | H+0 | Rp 4.000 |
| VA BSI | H+2 | Rp 3.500 |
| VA BCA | H+2 | Rp 4.500 |
| VA BCA | H+4 (S&K) | Rp 3.500 |
| QRIS dinamis ≤ Rp 100.000 | H+2 / H+0 | 0,5% / 0,5% + 1,8% |
| QRIS dinamis > Rp 100.000 | H+2 / H+0 | 0,7% / 0,7% + 1,8% |
| QRIS statis (offline) | H+2 | 0%–0,3% |
| E-wallet (ShopeePay, DANA) | H+3 | 3,5% |
| Alfamart / Indomaret | H+3 / H+0 | Rp 4.000 / Rp 4.000 + 1,8% |
| Kartu kredit | H+3 | 2,5% + Rp 2.000 |
| Direct debit GPN/BNI/BTN | H+2 | 1,4% + Rp 2.000 |
| Direct debit Mandiri/BRI/BNC | H+2 | Rp 5.000 |
| Cicilan tanpa kartu | H+0 | 1,5% + Rp 5.000 |
| Penarikan dana | H+0 | Rp 3.000 (Rp 7.500 untuk bank non-BI-Fast) |

- **Termasuk atau tidak termasuk PPN: TIDAK TERVERIFIKASI.** Halaman harga (EN dan ID) tidak memuat kata PPN, pajak, VAT, atau tax.
- Fee per kanal per akun juga tersedia lewat API: `GET /api/v2/payment-channels` mengembalikan `TransactionFee {ActualFee, ActualFeeType (FLAT|PERCENT), AdditionalFee}` per channel. Contoh di dokumentasi: VA BCA `ActualFee: 4000, FLAT`. Dibaca langsung: [Payment Channels](https://docs.ipaymu.com/id/docs/payment/payment-channels).

### 4b. API v2
Sumber: [docs.ipaymu.com](https://docs.ipaymu.com/id/docs) dan koleksi Postman resmi "iPaymu Public API v2" ([documenter](https://documenter.getpostman.com/view/40296808/2sB3WtseBT), dibaca lewat JSON koleksinya). Keduanya ditautkan dari ipaymu.com.

- **Base URL:** production `https://my.ipaymu.com`, sandbox `https://sandbox.ipaymu.com`. VA dan API Key sandbox berbeda dari production.
- **Header:** `Content-Type: application/json`, `va`, `signature`, `timestamp` (format `YYYYMMDDhhmmss`).
- **Signature request** ([Pembuatan Signature](https://docs.ipaymu.com/id/docs/signature)): `StringToSign = METHOD + ":" + VA + ":" + lowercase_hex(SHA256(bodyJson)) + ":" + APIKey`; `signature = hex(HMAC-SHA256(StringToSign, APIKey))`. Pre-request script di Postman sama (CryptoJS `SHA256(reqJson)` → hex). Body JSON yang di-hash harus string yang persis sama dengan yang dikirim. Untuk GET, dokumentasi menyebut query parameter yang di-stringify JSON.
- **Redirect payment** `POST /api/v2/payment` ([Redirect Payment](https://docs.ipaymu.com/id/docs/payment/redirect-payment)): wajib `product[]`, `qty[]`, `price[]`, `description[]`, `returnUrl`, `notifyUrl`, `cancelUrl`. Opsional: `referenceId`, `buyerName/Email/Phone`, `expired` (jam), `feeDirection` (`BUYER`|`MERCHANT`), `paymentMethod` (pra-seleksi: `va`, `banktransfer`, `cstore`, `cod`, `qris`, `cc`), `account` (child VA), `imageUrl[]`. Respons: `Data.SessionID`, `Data.Url`.
- **Direct payment** `POST /api/v2/payment/direct`: wajib `name`, `phone`, `email`, `amount`, `notifyUrl`, `referenceId`, `paymentMethod` (`va`, `cstore`, `cod`, `qris`, `cc`, `paylater`), `paymentChannel` (VA: `bag`, `bca`, `bpd_bali`, `bni`, `cimb`, `mandiri`, `bmi`, `bri`, `bsi`, `permata`, `danamon`; cstore: `alfamart`, `indomaret`; QRIS: `mpm`). Opsional: `expired`, `comments`, `feeDirection`, `escrow`, `product[]`. Batas `expired`: BSI VA maks 3 jam, BRI VA maks 2 jam, BCA VA tetap 12 jam, Alfamart tetap 24 jam, QRIS tetap 5 menit. Respons: `TransactionId`, `SessionId`, `ReferenceId`, `Via`, `Channel`, `PaymentNo`, `PaymentName`, `Total`, `Fee`, `Expired`.
- **`feeDirection`:** "MERCHANT => fee charged to merchant, BUYER => fee charged to buyer" (Postman). Kanal yang dipakai dan nilai default tidak dijelaskan lebih lanjut (**TIDAK TERVERIFIKASI**). Ingat larangan surcharge di bagian 3.
- **Cek transaksi** `POST /api/v2/transaction` body `{transactionId, account?}` ([Check Transaction](https://docs.ipaymu.com/id/docs/transaction/check-transaction)). Respons `Data.Status`: 0 pending, 1 berhasil, 2 batal, 3 refund, 4 error, 5 gagal, 6 berhasil-belum-settle, 7 escrow, -2 expired. Postman menulis "Status… dianggap berhasil: 1 atau 6 atau 7". Respons juga memuat `Amount`, `Fee`, `PaidStatus`, `SettlementDate`.
- **Validasi IP & domain production:** IP server harus statis dan didaftarkan. Domain di `returnUrl/notifyUrl/cancelUrl` harus didaftarkan (verifikasi maksimal 2 hari kerja). Localhost tidak bisa dipakai. Dibaca langsung: [Validasi IP & Domain](https://docs.ipaymu.com/id/docs/ip-domain-validation).

### 4c. Callback / notify
Dibaca langsung: [Callback](https://docs.ipaymu.com/id/docs/callback) dan item "Callback Params" di Postman.
- Dikirim POST ke `notifyUrl`. Content-Type diatur di dashboard: `application/x-www-form-urlencoded` (default) atau `application/json`. Callback di-retry sampai mendapat HTTP 200, jadi penanganannya harus idempoten.
- Header: `X-Signature`, `X-Timestamp`, `X-External-ID`.
- Field: `trx_id`, `sid`, `reference_id`, `status` (`berhasil`/`pending`/`expired`), `status_code` (1/0/-2), `status_desc`, `sub_total`, `total`, `amount`, `fee`, `paid_off` (= amount − fee), `created_at`, `expired_at`, `paid_at`, `settlement_status` (`settled`/`unsettle`), `transaction_status_code` (1 = langsung settle, 6 = diterima, settle terpisah), `is_escrow`, `via`, `channel`, `payment_no`, `va`, `buyer_name/email/phone`, `additional_info`, `url`, `system_notes`, `merchant`, `trscode`, `is_sandbox`, dan lain-lain.
- **Verifikasi:** secret key = **nomor VA merchant** (bukan API key). Langkahnya: ambil body; buang `signature`; normalisasi tipe (`trx_id`, `status_code`, `transaction_status_code`, `paid_off` → integer; `is_escrow` → boolean; `additional_info` → array, tambahkan `[]` jika tidak ada); `ksort`; `json_encode` (dengan escape `/` → `\/` seperti PHP); lalu `hash_hmac('sha256', json, VA)`. Bandingkan hasilnya dengan header `X-Signature` memakai `hash_equals`.
- Ada inkonsistensi antar sumber: Postman mengambil signature dari parameter body `signature` dan tidak menyebut normalisasi tipe, sedangkan docs.ipaymu.com memakai header `X-Signature` dengan normalisasi. Implementasi sebaiknya mencoba keduanya dan diuji di sandbox. Perilaku production: **TIDAK TERVERIFIKASI**.
- Praktik aman tambahan: konfirmasi status lewat `POST /api/v2/transaction` sebelum menandai lunas.

## 5. Bisakah biaya Xendit yang sebenarnya diambil otomatis lewat API?
- **(a) API daftar harga/fee schedule: tidak ada.** Indeks dokumentasi resmi [docs.xendit.co/llms.txt](https://docs.xendit.co/llms.txt) tidak memuat endpoint pricing atau fee schedule. Halaman fee hanya merujuk ke halaman harga web dan Billing Reports ([Transaction fees](https://docs.xendit.co/docs/transaction-fees.md)): "your specific transaction rates might vary based on what is in your contract". Bandingkan dengan iPaymu yang punya `GET /api/v2/payment-channels` (bagian 4a).
- **(b) Transactions API.** Dibaca langsung: [List Transactions](https://docs.xendit.co/apidocs/list-transactions.md) dan [Get Transaction by ID](https://docs.xendit.co/apidocs/get-transaction.md).
  - `GET https://api.xendit.co/transactions` (Basic auth, permission `Transaction Read`). Filter: `types` (misalnya `PAYMENT`), `statuses`, `channel_categories` (`VIRTUAL_ACCOUNT`, `QR_CODE`, `EWALLET`, `RETAIL_OUTLET`, `CARDS`, …), `reference_id` (exact, case-sensitive), `product_id` (exact), `account_identifier`, `currency`, `amount`, `created[gte|lte]`, `updated[gte|lte]`, `limit`, `after_id`/`before_id`.
  - `GET /transactions/{transaction_id}` untuk ID `txn_…`.
  - Field: `id`, `product_id`, `type`, `status` (`PENDING`/`SUCCESS`/`FAILED`/`VOIDED`/`REVERSED`), `channel_category`, `channel_code`, `reference_id`, `amount`, `net_amount` ("after it deducted with fee/vat"), `cashflow` (`MONEY_IN`/`MONEY_OUT`), `settlement_status` (`PENDING`/`EARLY_SETTLED`/`SETTLED`), `estimated_settlement_time`, `fee {xendit_fee, value_added_tax, xendit_withholding_tax, third_party_withholding_tax, status}`.
  - `product_data.payment_link_id` ("The invoice/payment link ID. Present for payments associated with payment links") menghubungkan transaksi ke invoice.
  - `fee.status`: `PENDING`, `COMPLETED`, `CANCELED`, `REVERSED`, `NOT_APPLICABLE`. Biaya dianggap final saat `COMPLETED`.
  - Menurut [Reconciliation](https://docs.xendit.co/docs/reconciliations.md), "Payment Link ID is the `invoice_id` in webhooks and reports".
  - Isi `reference_id` untuk transaksi invoice (apakah sama dengan `external_id`): **TIDAK TERVERIFIKASI**.
  - **Keterbatasan:**
    - Processing fee dipotong harian sekaligus ([Transaction fees](https://docs.xendit.co/docs/transaction-fees.md)), jadi kemungkinan tidak masuk `fee.xendit_fee` per transaksi (**TIDAK TERVERIFIKASI**).
    - Biaya dengan deduction type **INDIRECT** ditagih bulanan; `fees_paid_amount`/`vat_paid_amount` di Billing report "not populated for indirect deductions" dan harus dihitung dari `fee_percentage` + `flat_fee` ([Billing report](https://docs.xendit.co/docs/billing-report.md)).
- **(c) Callback invoice tidak membawa biaya Xendit yang andal.** `fees` adalah biaya yang dibayar pelanggan (dari request). `fees_paid_amount`/`adjusted_received_amount` adalah biaya Xendit tetapi deprecated sejak 18 Mei 2022 dan hanya ada untuk VA non-switching dan ritel (bagian 2c).
- **(d) Balance / Report API.**
  - `GET /balance` hanya mengembalikan saldo ([Get balance](https://docs.xendit.co/apidocs/get-balance.md)).
  - `POST /reports` dengan `type`: `BALANCE_HISTORY`, `TRANSACTIONS`, `UPCOMING_TRANSACTIONS`, `DETAILED_TRANSACTIONS`; `VERSION_2` menambahkan kolom early settlement fee ([Generate Report](https://docs.xendit.co/apidocs/generate-report.md)). Hasilnya diambil dengan `GET /reports/{id}`.
  - Kolom laporan transaksi: Total Fee, Total VAT, Net Amount, Invoice ID, Transaction Fee Deduction Type (DIRECT/INDIRECT) ([Transactions report](https://docs.xendit.co/docs/transactions-report.md), [Detailed transactions report](https://docs.xendit.co/docs/detailed-transactions-report.md)).
- **Saran alur:** setelah callback invoice `PAID`, jalankan job tertunda yang memanggil `GET /transactions?types=PAYMENT&…` lalu mencocokkan `product_data.payment_link_id` dengan ID invoice. Simpan `fee.xendit_fee + fee.value_added_tax` saat `fee.status = COMPLETED`. Rekonsiliasi bulanan dengan Billing report untuk processing fee dan biaya indirect.

## 6. Pengganti non-legacy: Payment Sessions (`POST /sessions`) dan Payments API v3
Semua dibaca langsung dari referensi OpenAPI yang tertanam di halaman `.md` docs.xendit.co (2026-10-03).

### 6a. Request hosted checkout: `POST https://api.xendit.co/sessions`
Sumber: [Create a session](https://docs.xendit.co/apidocs/create-session.md).

- **Wajib:**
  - `reference_id`
  - `session_type`: `PAY` | `SAVE` | `SUBSCRIPTION`
  - `currency`: `IDR`, …
  - `amount`: satu nominal; harus 0 untuk SAVE
  - `mode`: `PAYMENT_LINK` (hosted) | `COMPONENTS`
  - `country`: `ID`, …
- **Opsional:**
  - `customer_id` atau objek `customer`. Isi `customer` adalah `type: INDIVIDUAL`, `reference_id` (wajib), `email`, `mobile_number` (E.164), dan `individual_detail.given_names` (wajib).
  - `allowed_payment_channels`: array string.
  - `channel_properties`: properti khusus kanal untuk provider.
  - `expires_at`: ISO 8601, default **30 menit** setelah dibuat.
  - `locale`, `metadata` (≤ 50 key), `description` (tampil di halaman checkout).
  - `items[]`: `reference_id`, `type` (`DIGITAL_PRODUCT` | `PHYSICAL_PRODUCT` | `DIGITAL_SERVICE` | `PHYSICAL_SERVICE` | **`FEE`**), `name`, `net_unit_amount`, `quantity`, `category`, …
  - `success_return_url`, `cancel_return_url`: wajib HTTPS.
  - `capture_method`, `allow_save_payment_method`, `notification_channels`.
- **Respons** (201): `payment_session_id` (prefix `ps-`), `status`, **`payment_link_url`** (alamat redirect pelanggan), `payment_request_id`, `payment_id`, `payment_token_id`, `business_id`.
- **Biaya / surcharge:** tidak ada field `fees` atau surcharge. `fees` versi legacy diganti item `items[].type = FEE` ([panduan migrasi](https://docs.xendit.co/docs/migrate-to-payment-session.md)). `amount` hanya satu angka dan item FEE tidak bisa dikaitkan ke kanal, jadi **nominal tidak bisa dibuat berbeda per kanal dalam satu session**. Dokumentasi tidak menyebut apakah jumlah `items` harus sama dengan `amount` (**TIDAK TERVERIFIKASI**). Larangan surcharge di bagian 3 tetap berlaku.
- **Payments API v3, alternatif tanpa hosted page** ([Create a payment request](https://docs.xendit.co/apidocs/create-payment-request.md)): `POST /v3/payment_requests` dengan `reference_id`, `type` (`PAY`, `PAY_AND_SAVE`, `REUSABLE_PAYMENT_CODE`, `VERIFY_PAYMENT_METHOD`), `country`, `currency`, `request_amount`, **`channel_code`** (satu kanal per request), `channel_properties`, `capture_method`, `description`, `customer`/`customer_id`, `metadata`. Skema ini juga tidak punya field fee. Dengan v3, UNMS harus membuat UI pilih kanal sendiri, lalu menampilkan nomor VA atau QR dari respons.

### 6b. Membatasi session ke satu kategori kanal
- `allowed_payment_channels` menerima **kode kanal**, bukan kategori. Contoh di dokumentasi: `["CARDS","BRI_DIRECT_DEBIT","DANA"]`. Tidak ada nilai seperti `VIRTUAL_ACCOUNT` atau `QR_CODE` di skema (dicek dengan grep pada OpenAPI). Untuk "hanya VA", daftarkan semua kode VA. Untuk "hanya QRIS", isi `["QRIS"]`.
- Kode kanal Indonesia, dibaca dari halaman kanal masing-masing (semua bertanda "Payment Link ✓"):
  - VA: `BCA_VIRTUAL_ACCOUNT`, `BNI_VIRTUAL_ACCOUNT`, `BRI_VIRTUAL_ACCOUNT`, `MANDIRI_VIRTUAL_ACCOUNT`, `PERMATA_VIRTUAL_ACCOUNT`, `BSI_VIRTUAL_ACCOUNT`, `BNC_VIRTUAL_ACCOUNT`, `BSS_VIRTUAL_ACCOUNT`, `MUAMALAT_VIRTUAL_ACCOUNT` (contoh: [BCA VA](https://docs.xendit.co/docs/bca-virtual-account.md))
  - QRIS: `QRIS` ([QRIS](https://docs.xendit.co/docs/qris.md))
  - Ritel: `ALFAMART`, `INDOMARET`
  - E-wallet: `DANA`, `OVO`, `LINKAJA`, `SHOPEEPAY`
  - Kartu: `CARDS`
- Kanal harus sudah aktif di akun. Panduan migrasi: "Omit to show all activated channels".

### 6c. Webhook
- **Session** ([webhook payment session](https://docs.xendit.co/apidocs/webhook-notification-sent-defined-webhook-url-updates-payment-session.md)):
  - Event: `payment_session.completed` dan `payment_session.expired`. Tidak ada webhook untuk CANCELED ([Overview](https://docs.xendit.co/docs/payment-sessions-overview.md)).
  - Body: `{event, business_id, created, data: {payment_session_id, reference_id, status (ACTIVE|COMPLETED|EXPIRED|CANCELED), amount, currency, session_type, mode, expires_at, payment_link_url, payment_request_id, payment_id, payment_token_id, customer_id, items, …}}`.
  - Payload session **tidak** memuat `channel_code`.
- **Payment** ([Payment webhook notification](https://docs.xendit.co/apidocs/payment-webhook-notification.md)):
  - Event: `payment.capture`, `payment.authorization`, `payment.failure`. Daftar event lain: `payment_request.expiry`, `refund.succeeded`/`failed` ([Payments API webhooks](https://docs.xendit.co/docs/payments-api-webhooks.md)).
  - Field `data`: `payment_id` (`py-`), `payment_request_id` (`pr-`), `reference_id`, `type`, `currency`, **`request_amount`**, **`channel_code`** (misalnya `DANA`), `status` (`SUCCEEDED`, `FAILED`, `EXPIRED`, `PENDING`, `AUTHORIZED`, `CANCELED`), `captures[] {capture_id, capture_amount, capture_timestamp}`, `payment_details {issuer_name, payer_name, receipt_id, …}`, `failure_code`, `metadata`.
  - Payload tidak memuat `payment_session_id`; penghubungnya `payment_request_id` atau `payment_id`. Apakah `reference_id` session diteruskan ke payment: **TIDAK TERVERIFIKASI**.
  - Panduan migrasi menyebut event sukses `payment.succeeded`, sedangkan referensi webhook menyebut `payment.capture`. Ikuti referensi API.
- **Pola yang dianjurkan:** status session menentukan apakah checkout selesai, sedangkan status Payment menentukan apakah uang benar-benar masuk ("rule of thumb" di panduan migrasi).
- **Verifikasi:** header `x-callback-token` dicocokkan dengan token di Dashboard > Webhook settings ([Handling webhooks](https://docs.xendit.co/docs/handling-webhooks.md)). Untuk idempotensi gunakan `payment_id` dan `capture_id`. Webhook harus dibalas 2xx; retry dilakukan 6× dengan backoff 15m, 1j, 3j, 6j, 12j, 24j ([Webhook behavior](https://docs.xendit.co/apidocs/webhook-behavior.md)). URL webhook session/payment diatur di dashboard, bukan per request (**TIDAK TERVERIFIKASI** untuk session; skema request tidak punya field URL webhook).

### 6d. Cek status, expire, batal
- Session:
  - `GET /sessions/{session_id}` ([Get session](https://docs.xendit.co/apidocs/get-session.md))
  - `POST /sessions/{session_id}/cancel` ([Cancel session](https://docs.xendit.co/apidocs/cancel-session.md)): "Cancels an ACTIVE Session. The Xendit Hosted Checkout URL will be invalid."
  - Tidak ada endpoint untuk memperpanjang atau mengubah `expires_at`. Session yang EXPIRED atau CANCELED tidak bisa diaktifkan lagi, jadi buat session baru.
- v3:
  - `GET /v3/payment_requests/{id}` ([Get](https://docs.xendit.co/apidocs/get-payment-request.md))
  - `POST /v3/payment_requests/{id}/cancel` ([Cancel](https://docs.xendit.co/apidocs/cancel-payment-request.md)): "Prevents end users from completing payment"
  - Ada pula endpoint update payment request ([Update](https://docs.xendit.co/apidocs/update-a-payment-request.md), isinya belum dibaca).

### 6e. Apakah session yang tidak dibayar dikenai Processing Fee?
- Kebijakan: processing fee berlaku untuk "all attempts of payment acceptance… including all failed transaction attempts" ([Pricing Policy](https://help.xendit.co/hc/en-us/articles/59516240127129-Xendit-Pricing-Policy)). Blog resmi (ekstrak pencarian, halaman diblokir 403) menyebut biaya ini "charged each time a transaction is initiated… regardless of whether it succeeds… calculated on a per-attempt basis" ([How Payment Processing Fees Are Calculated](https://www.xendit.co/en/blog/how-payment-processing-fees-are-calculated/)).
- **Apa yang dihitung sebagai "attempt" tidak didefinisikan** di sumber mana pun yang terbaca. Belum jelas apakah pembuatan session saja sudah dihitung, apakah dihitung saat pelanggan memilih kanal (misalnya saat nomor VA atau QR dibuat), atau hanya saat ada percobaan bayar ke provider. **TIDAK TERVERIFIKASI.** Session memang "Supports multiple payment attempts" ([Overview](https://docs.xendit.co/docs/payment-sessions-overview.md)), jadi satu session bisa menghasilkan lebih dari satu attempt.
- **Implikasi praktis:** jangan membuat session atau payment request secara massal untuk semua tagihan. Buat hanya saat pelanggan menekan "Bayar". Session sendiri disarankan "short-lived… create a new Session again only when the customer is ready to make payment" (deskripsi `expires_at`). Konfirmasi ke Xendit, atau uji satu session yang dibiarkan expired lalu cek Billing report.

### 6f. Tautan Transactions API ke session/payment
- [List Transactions](https://docs.xendit.co/apidocs/list-transactions.md): untuk Payments v3, `product_id` berisi Payment ID `py-…` (contoh di dokumentasi). `product_data` memuat `payment_request_id` (`pr-`), `capture_id` (`cptr-`), `payment_link_id` ("invoice/payment link ID", contoh `inv-…`), dan `reusable_payment_link_id`.
- Field `payment_session_id` (`ps-`) **tidak ada** di objek transaksi. Hubungkan lewat `payment_id` atau `payment_request_id` yang ada di webhook dan objek session, misalnya `GET /transactions?product_id=py-…`. Apakah `payment_link_id` terisi untuk transaksi dari session: **TIDAK TERVERIFIKASI**.

## Celah
- Angka harga Xendit saat ini (bagian 1b) belum dibaca langsung dari halaman (blokir 403). Verifikasi di Dashboard > Settings > Billing and Fees, atau dengan satu transaksi uji lalu baca `fee` di Transactions API.
- Belum diketahui apakah processing fee Xendit dikenai PPN dan apakah masuk ke `fee.xendit_fee`.
- Belum diketahui apakah Xendit/iPaymu menerapkan MDR QRIS 0% (≤ Rp 100.000, mulai 1 Okt 2026).
- Status PPN pada harga iPaymu belum diketahui.
- Format signature callback iPaymu di production (header vs body) belum dipastikan.
- Belum jelas apa yang dihitung sebagai "attempt" untuk Processing Fee pada Payment Session (pembuatan session, pemilihan kanal, atau percobaan bayar).
