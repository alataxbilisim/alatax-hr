> **Birleştirildi:** İçerik `docs/SISTEM_ISLEYIS.md` → AŞAMA 1 (Sistem panosu düzeni) bölümüne taşındı (2026-08-06).

# Sistem panosu düzeni (arşiv kopyası)

Sistem panosu (`dashboards.is_system = true`) tek paylaşımlı satırdır; `layout` JSON'u tüm kullanıcılar için ortaktır — kullanıcı başına saklanmaz. Bu yüzden sistem panosu **salt okunur**dur: widget taşıma/kaydetme API'de 403. Özelleştirme yolu: **Kopyala** → firma panosu (`is_system=false`, sahip = kopyalayan); düzen yalnızca o kopyada saklanır, diğer kullanıcıların sistem görünümünü ve birbirinin kopyasını etkilemez.
