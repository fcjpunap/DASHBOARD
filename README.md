# Dashboard de Criminalidad SIDPOL & MPFN (Future Ready v5.0)

Este sistema es una herramienta avanzada de Business Intelligence (BI) para la visualización y análisis de estadísticas de criminalidad en el Perú. Permite consolidar datos de fuentes oficiales como SIDPOL (Policía) y MPFN (Fiscalía) en un tablero dinámico e interactivo.

## 🚀 Innovaciones Técnicas (Versión 2026+)

Esta versión incluye mejoras críticas para la integridad de los datos y la adaptabilidad del sistema:

### 📅 Arquitectura "Future-Ready"
El importador ya no depende de años fijos. Mediante una lógica de **Ventana Rodante**, el sistema detecta automáticamente el año más reciente en los archivos de SIDPOL (ej. 2026, 2027) y reconfigura internamente las reglas de importación de las Hojas 5, 6 y 7 del Excel sin necesidad de intervención manual o cambios en el código.

### 🧹 Limpieza Inteligente (Smart Clean)
Se ha implementado una herramienta de deduplicación robusta que:
- **Normaliza Nombres:** Elimina espacios en blanco residuales y diferencias de mayúsculas/minúsculas (ej. "LIMA " vs "lima").
- **Detección de Traslapes:** Identifica si un registro se encuentra duplicado entre diferentes hojas del mismo Excel (problema común en SIDPOL donde los años a veces se solapan entre hojas históricas y actuales).
- **Consistencia de Cifras:** Al eliminar estas redundancias, garantiza que el KPI total coincida exactamente con las cifras oficiales del Mininter.

### 🛡️ Hash Único Evolucionado
El sistema de deduplicación por Hash MD5 ha sido actualizado para ignorar la columna `cantidad` en su cálculo. Esto permite que, si el Mininter actualiza el conteo de denuncias de un mes ya importado, el sistema actualice el valor (`ON DUPLICATE KEY UPDATE`) en lugar de crear una fila duplicada.

## 📊 Características del Sistema

- **Consolidación Multifuente:** Procesamiento de archivos `.xlsx` (SIDPOL) y `.csv` (MPFN/IGF).
- **Análisis de Violencia Detallado:** Importación especializada para la base de Violencia Mujer/IGF con limpieza automática de registros genéricos para evitar duplicidad.
- **KPIs Dinámicos:** Seguimiento de Delitos, Faltas, Violencia y Niños.
- **Filtros Geográficos:** Desglose completo desde nivel nacional hasta distrital.
- **Detección de Fuente Automática:** El sistema identifica la estructura del archivo (Policía vs Fiscalía) al momento de la carga.

## 📂 Fuentes de Datos

El sistema se alimenta de:
1.  **SIDPOL (Mininter):** Datos sobre hechos delictivos del [Observatorio Mininter](https://observatorio.mininter.gob.pe/).
2.  **MPFN (Ministerio Público):** Datos abiertos sobre delitos denunciados ante las fiscalías.

## 🛠️ Requisitos e Instalación

- **Entorno:** PHP 7.4+ | MySQL/MariaDB (InnoDB).
- **Extensiones:** XMLReader, ZipArchive, PDO_MySQL, mbstring.

### Instalación:
1. Configurar credenciales en `admin/db.php`.
2. Importar `database_schema.sql` en su servidor MariaDB.
3. El primer acceso al `/admin/panel.php` permitirá limpiar y realizar la primera importación masiva.

---

## ⚠️ Disclaimer
Este dashboard es una herramienta de visualización basada en datos estadísticos oficiales. Los reportes generados son referenciales y dependen de la actualización de los portales de Gobierno Abierto.

---

## 👨‍💻 Autoría y Reconocimientos
**Autor Principal:** Michael Espinoza Coila.  
*Este sistema de Business Intelligence y Legal Tech ha sido desarrollado íntegramente por el autor, contando con la asistencia técnica de inteligencia artificial avanzada, específicamente **Gemini 3 Flash y Pro** y **Claude 4.5 Opus**, quienes colaboraron en la estructuración de la lógica algorítmica y revisión del código.*

---
© 2026 Michael Espinoza Coila - Optimizado para el manejo de grandes volúmenes de datos y registros históricos de criminalidad.
