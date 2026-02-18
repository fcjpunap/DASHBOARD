# Dashboard de Denuncias (SIDPOL & MPFN)

Este sistema es una herramienta avanzada de visualización y análisis de estadísticas de criminalidad en el Perú. Permite consolidar y filtrar datos provenientes de las principales fuentes oficiales del país para facilitar la toma de decisiones basada en evidencia.

## 📊 Características del Sistema

- **Consolidación Multifuente:** Importación y procesamiento automático de datos de SIDPOL (Policía Nacional) y MPFN (Ministerio Público).
- **KPIs Dinámicos:** Visualización en tiempo real de delitos totales, violencia, faltas y tendencias.
- **Análisis Geográfico:** Filtros detallados por departamento, provincia y distrito.
- **Composición del Delito:** Gráficos detallados sobre la naturaleza de las denuncias registradas.
- **Ranking Nacional:** Comparativa interactiva entre regiones.
- **Deduplicación Inteligente:** Sistema basado en hashes MD5 para evitar entradas duplicadas entre diferentes hojas de cálculo o fuentes.

## 📂 Fuentes de Datos

El sistema se alimenta de bases de datos de acceso público y gobierno abierto:

1.  **SIDPOL (Mininter):** Datos sobre hechos delictivos basados en denuncias policiales. [Portal Observatorio Mininter](https://observatorio.mininter.gob.pe/).
2.  **MPFN (Ministerio Público):** Datos abiertos sobre delitos denunciados ante las fiscalías. [Portal de Datos Abiertos del Perú](https://www.datosabiertos.gob.pe/dataset/mpfn-delitos-denunciados).

---

## ⚠️ Disclaimer (Descargo de Responsabilidad)

**Aclaración sobre el uso de la información:**
1.  **Naturaleza de los Datos:** Este dashboard es una herramienta de visualización de datos estadísticos. La precisión, integridad y actualidad de la información dependen exclusivamente de las fuentes originales (SIDPOL y MPFN).
2.  **Uso No Oficial:** Aunque utiliza fuentes públicas oficiales, este sistema no es un canal oficial de comunicación del Ministerio del Interior ni del Ministerio Público. Los reportes generados deben considerarse referenciales.
3.  **Responsabilidad:** El desarrollador y los colaboradores no se hacen responsables por decisiones tomadas, interpretaciones erróneas o acciones legales basadas en el uso de esta herramienta.
4.  **Privacidad:** El sistema solo procesa datos estadísticos anonimizados según lo provisto en los portales de datos abiertos; no contiene información que permita identificar personas naturales.

---

## 🛠️ Requisitos e Instalación

- **Backend:** PHP 7.4 o superior.
- **Base de Datos:** MySQL / MariaDB (InnoDB).
- **Extensiones PHP:** PDO_MySQL, XMLReader, ZipArchive, mbstring.

### Instalación rápida:

1.  Clonar el repositorio.
2.  Configurar la conexión en `admin/db.php`.
3.  Importar el esquema inicial de la base de datos.
4.  Acceder al panel `/admin/` para iniciar la importación de datos SIDPOL/MPFN.

## 📄 Licencia

Este proyecto está bajo la licencia **GNU GPL v3**. Consulte el archivo `LICENSE` para más detalles.

---
© 2026 Michael Espinoza Coila - Asistido por inteligencia artificial (Antigravity & Claude).
