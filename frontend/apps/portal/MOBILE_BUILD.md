# ALATAX Portal - Mobil Uygulama Build Rehberi

## Gereksinimler

### Android
- Node.js 18+
- Android Studio (Android SDK 33+)
- Java 17+

### iOS
- macOS
- Xcode 15+
- CocoaPods

## Kurulum

### 1. Bağımlılıkları Yükle

```bash
cd frontend/apps/portal
npm install
npm install @capacitor/core @capacitor/cli @capacitor/android @capacitor/ios
npm install @capacitor/splash-screen @capacitor/status-bar @capacitor/push-notifications
npm install @capacitor/geolocation @capacitor/camera @capacitor/keyboard
```

### 2. Capacitor'u Başlat

```bash
npx cap init "ALATAX Portal" com.alatax.portal
```

### 3. Web Uygulamasını Build Et

```bash
npm run build
```

### 4. Platform Ekle

#### Android
```bash
npx cap add android
```

#### iOS
```bash
npx cap add ios
```

## Android APK Build

### 1. Web Build
```bash
npm run build
```

### 2. Android Senkronize Et
```bash
npx cap sync android
```

### 3. Android Studio'da Aç
```bash
npx cap open android
```

### 4. APK Oluştur
Android Studio'da:
1. `Build > Build Bundle(s) / APK(s) > Build APK(s)`
2. Debug APK: `android/app/build/outputs/apk/debug/app-debug.apk`

### Release APK İçin:
1. `Build > Generate Signed Bundle / APK`
2. Keystore oluştur veya mevcut olanı seç
3. Release APK oluştur

## iOS Build

### 1. Web Build
```bash
npm run build
```

### 2. iOS Senkronize Et
```bash
npx cap sync ios
```

### 3. Xcode'da Aç
```bash
npx cap open ios
```

### 4. Build ve Deploy
Xcode'da:
1. Team seç (Apple Developer hesabı gerekli)
2. `Product > Archive` ile dağıtım için arşivle
3. TestFlight veya App Store'a yükle

## Canlı Geliştirme (Live Reload)

Development sırasında değişiklikleri anında görmek için:

### 1. capacitor.config.ts Güncelle
```typescript
server: {
  url: 'http://YOUR_LOCAL_IP:5173',
  cleartext: true,
}
```

### 2. Dev Server Başlat
```bash
npm run dev -- --host
```

### 3. Uygulamayı Çalıştır
```bash
npx cap run android
# veya
npx cap run ios
```

## Icon ve Splash Screen

### Android
- `android/app/src/main/res/` klasöründe:
  - `mipmap-*/ic_launcher.png` - Uygulama ikonu
  - `drawable/splash.png` - Splash ekranı

### iOS
- `ios/App/App/Assets.xcassets/` klasöründe:
  - `AppIcon.appiconset/` - Uygulama ikonu
  - `Splash.imageset/` - Splash ekranı

## Push Notification Yapılandırması

### Firebase (Android)
1. Firebase Console'da proje oluştur
2. `google-services.json` dosyasını `android/app/` klasörüne koy
3. Firebase Cloud Messaging'i etkinleştir

### APNs (iOS)
1. Apple Developer Portal'da Push Notification sertifikası oluştur
2. Xcode'da Push Notifications capability ekle
3. APNs key'i Firebase'e veya kendi sunucunuza ekle

## Sorun Giderme

### Android: cleartext HTTP izni (development için)
`android/app/src/main/AndroidManifest.xml`:
```xml
<application android:usesCleartextTraffic="true">
```

### iOS: Info.plist ayarları
```xml
<key>NSAppTransportSecurity</key>
<dict>
    <key>NSAllowsArbitraryLoads</key>
    <true/> <!-- Sadece development için -->
</dict>
```

### Geolocation izinleri
Android: `AndroidManifest.xml`'e eklendi
iOS: `Info.plist`'e NSLocationWhenInUseUsageDescription ekle

## Performans Optimizasyonları

1. **Lazy Loading**: Sayfa bileşenlerini lazy load yap
2. **Image Optimization**: WebP formatı kullan
3. **Bundle Size**: Tree shaking aktif, kullanılmayan kodu çıkar
4. **Caching**: Service Worker ile offline cache

## Test Kontrol Listesi

- [ ] Giriş/Çıkış akışı çalışıyor
- [ ] Puantaj giriş/çıkış butonu çalışıyor
- [ ] İzin talebi oluşturulabiliyor
- [ ] Masraf talebi oluşturulabiliyor ve fiş yüklenebiliyor
- [ ] Push notification alınıyor
- [ ] Offline modda uygulama açılıyor
- [ ] GPS lokasyonu alınabiliyor
- [ ] Kamera izni ve fotoğraf çekimi çalışıyor
- [ ] Bottom navigation düzgün görünüyor
- [ ] Safe area'lar düzgün işliyor (notch, home indicator)

