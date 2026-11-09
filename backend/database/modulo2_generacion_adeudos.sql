USE alkira;

-- 1. Revisar duplicados existentes.
SELECT
    id_contrato,
    id_concepto,
    periodo,
    COUNT(*) AS cantidad
FROM estados_cuenta
GROUP BY
    id_contrato,
    id_concepto,
    periodo
HAVING COUNT(*) > 1;

-- 2. Comprobar si el indice ya existe.
SET @indiceExiste = (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'estados_cuenta'
      AND INDEX_NAME = 'uk_ec_contrato_concepto_periodo'
);

-- 3. Crear el indice solo si no existe.
SET @sentenciaIndice = IF(
    @indiceExiste = 0,
    'ALTER TABLE estados_cuenta
     ADD UNIQUE KEY uk_ec_contrato_concepto_periodo
     (id_contrato, id_concepto, periodo)',
    'SELECT ''El indice ya existe'' AS resultado'
);

PREPARE sentenciaPreparada FROM @sentenciaIndice;

EXECUTE sentenciaPreparada;

DEALLOCATE PREPARE sentenciaPreparada;

-- 4. Comprobar el resultado.
SHOW INDEX FROM estados_cuenta;
