# Değişiklik Kaydı

## 1.0.0

İlk sürüm.

- cPanel/WHM ve Plesk sunucularını tek modülden yönetir; panel tipi sunucu başına otomatik
  algılanır, böylece tek bir ürün her iki panel tipini barındıran bir sunucu grubuna bağlanabilir.
- Hesap oluşturma, askıya alma, geri alma, sonlandırma, şifre ve paket değişikliği.
- Müşteri alanından tek tıkla kontrol paneli girişi (cPanel yönlendirme, Plesk form tabanlı).
- Disk ve trafik kullanımının WHMCS'e günlük senkronu.
- cPanel'de reseller paket öneki (`kullanici_paket`) şeffaf şekilde çözülür.
- Plesk'te abonelik işlemleri `<webspace>` operatörü üzerinden yürür; paket modülün
  `<domain>` tabanlı istekleri güncel Plesk sürümlerinde reddediliyor.
- Yazma işlemlerinden sonra durum panelden geri okunarak doğrulanır.
- Panel tipi ve protokol sürümü sunucu başına yedi gün önbelleklenir.
- API token'ları ve müşteri şifreleri kayıtlara düz metin olarak yazılmaz.
