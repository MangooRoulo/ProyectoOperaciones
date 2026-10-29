<?php
require_once __DIR__ . '/php/simplex.php';

// Inicia el formulario vacio para que cada problema se construya desde cero.
$defaultObjective = '';
$defaultConstraints = [
    ['coefficients' => '', 'rhs' => ''],
];

// Guarda los datos mostrados en el formulario y el resultado actual.
$objectiveInput = $defaultObjective;
$constraintsInput = $defaultConstraints;
$result = null;
$model = null;
$variableCountInput = 2;
$constraintCountInput = count($defaultConstraints);
$errors = [];

// Procesa el formulario solo cuando el usuario solicita resolver el problema.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $objectiveInput = trim((string) ($_POST['objective'] ?? ''));
    $variableCountInput = (int) ($_POST['variable_count'] ?? 2);
    $constraintCountInput = (int) ($_POST['constraint_count'] ?? 1);
    $postedConstraints = $_POST['constraints'] ?? [];
    $constraintsInput = [];

    // Conserva los valores escritos para que un error no borre el formulario.
    foreach ($postedConstraints as $constraint) {
        $constraintsInput[] = [
            'coefficients' => trim((string) ($constraint['coefficients'] ?? '')),
            'rhs' => trim((string) ($constraint['rhs'] ?? '')),
        ];
    }

    try {
        // Lee y valida la funcion objetivo.
        $objective = parseNumberList($objectiveInput);

        if (count($objective) < 2 || count($objective) > 6) {
            throw new InvalidArgumentException('Usa entre 2 y 6 variables de decision.');
        }

        if (count($constraintsInput) < 1 || count($constraintsInput) > 8) {
            throw new InvalidArgumentException('Usa entre 1 y 8 restricciones.');
        }

        $constraints = [];
        $rhs = [];

        // Comprueba que cada restriccion tenga la misma cantidad de coeficientes.
        foreach ($constraintsInput as $index => $constraintInput) {
            $coefficients = parseNumberList($constraintInput['coefficients']);

            if (count($coefficients) !== count($objective)) {
                throw new InvalidArgumentException(
                    'La restriccion ' . ($index + 1) . ' debe tener ' . count($objective) . ' coeficientes.'
                );
            }

            if ($constraintInput['rhs'] === '' || !is_numeric(str_replace(',', '.', $constraintInput['rhs']))) {
                throw new InvalidArgumentException('El lado derecho de la restriccion ' . ($index + 1) . ' no es valido.');
            }

            $constraintRhs = (float) str_replace(',', '.', $constraintInput['rhs']);

            if ($constraintRhs < 0) {
                throw new InvalidArgumentException('Los lados derechos deben ser no negativos en esta version.');
            }

            $constraints[] = $coefficients;
            $rhs[] = $constraintRhs;
        }

        // Ejecuta el algoritmo una vez que todos los datos pasaron la validacion.
        $result = simplex($objective, $constraints, $rhs);
        $model = [
            'objective' => $objective,
            'constraints' => $constraints,
            'rhs' => $rhs,
        ];
    } catch (InvalidArgumentException $exception) {
        // Muestra errores de entrada cerca del formulario sin detener la pagina.
        $errors[] = $exception->getMessage();
    }
}

function displayNumber(float $value): string
{
    // Usa hasta cuatro decimales y elimina ceros innecesarios.
    return rtrim(rtrim(number_format($value, 4, '.', ''), '0'), '.');
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Simplex Lab | Programacion lineal</title>
    <link rel="stylesheet" href="index.css">
</head>

<body>
    <header class="hero">
        <div class="hero-content"></div>
    </header>

    <main class="layout">
        <section class="panel" aria-labelledby="model-title">
            <div class="panel-heading">
                <span class="step-tag">Paso 1 de 3</span>
                <h2 id="model-title">Define tu modelo</h2>
                <p>Maximizacion con variables no negativas y restricciones del tipo menor o igual.</p>
            </div>

            <?php if ($errors): ?>
                <div class="alert" role="alert">
                    <?php foreach ($errors as $error): ?>
                        <div><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="post" id="simplex-form">
                <div class="model-size">
                    <div class="field">
                        <label for="variable-count">Número de incógnitas</label>
                        <input id="variable-count" name="variable_count" type="number" min="2" max="6" value="<?= $variableCountInput ?>" required>
                    </div>
                    <div class="field">
                        <label for="constraint-count">Cantidad de restricciones</label>
                        <input id="constraint-count" name="constraint_count" type="number" min="1" max="8" value="<?= $constraintCountInput ?>" required>
                    </div>
                    <button type="button" class="add-button" id="generate-model">Generar campos</button>
                </div>
                <div class="field">
                    <div class="label-line">
                        <label for="objective">Función objetivo · Maximizar Z =</label>
                        <button type="button" class="help-toggle" aria-label="Ayuda para la función objetivo" aria-expanded="false" data-help="Escribe un coeficiente por cada variable, separado por comas. Ejemplo: 3, 5 significa Z = 3x1 + 5x2.">!</button>
                    </div>
                    <input id="objective" name="objective" value="<?= htmlspecialchars($objectiveInput, ENT_QUOTES, 'UTF-8') ?>" placeholder="Ej. 3, 5" required>
                </div>

                <div class="field">
                    <div class="label-line">
                        <label>Restricciones</label>
                        <button type="button" class="help-toggle" aria-label="Ayuda para las restricciones" aria-expanded="false" data-help="Cada fila representa una restricción. En coeficientes escribe los valores de x1, x2, etc.; en Límite RHS escribe el recurso máximo disponible.">!</button>
                    </div>
                    <div id="constraints-container">
                        <?php foreach ($constraintsInput as $index => $constraint): ?>
                            <div class="constraint-row">
                                <div class="constraint-number">R<?= $index + 1 ?></div>
                                <div class="field">
                                    <div class="field-label-line">
                                        <span class="field-label">Coeficientes</span>
                                        <button type="button" class="help-toggle" aria-label="Ayuda para coeficientes" aria-expanded="false" data-help="Escribe un número por variable. Ejemplo: 1, 2 representa 1x1 + 2x2.">!</button>
                                    </div>
                                    <input name="constraints[<?= $index ?>][coefficients]" value="<?= htmlspecialchars($constraint['coefficients'], ENT_QUOTES, 'UTF-8') ?>" placeholder="Ej. 1, 2" required aria-label="Coeficientes de restriccion <?= $index + 1 ?>">
                                </div>
                                <div class="field">
                                    <div class="field-label-line">
                                        <span class="field-label">Límite RHS</span>
                                        <button type="button" class="help-toggle" aria-label="Ayuda para el límite RHS" aria-expanded="false" data-help="Escribe el límite de la restricción. Ejemplo: 10 significa que la suma no puede superar 10.">!</button>
                                    </div>
                                    <input name="constraints[<?= $index ?>][rhs]" value="<?= htmlspecialchars($constraint['rhs'], ENT_QUOTES, 'UTF-8') ?>" placeholder="Ej. 10" required aria-label="Lado derecho de restriccion <?= $index + 1 ?>">
                                </div>
                                <button type="button" class="icon-button remove-constraint" title="Eliminar restriccion" aria-label="Eliminar restriccion">×</button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="add-button" id="add-constraint">+ Agregar restriccion</button>
                </div>

                <button type="submit" class="solve-button">Paso 3 · Resolver con Simplex <span aria-hidden="true">→</span></button>
            </form>
        </section>

        <section class="panel" aria-labelledby="result-title">
            <?php if (!$result): ?>
                <div class="empty-state">
                    <div>
                        <strong id="result-title">Tu resultado aparecera aqui</strong>
                        <span>Completa el modelo y observa como cada pivote acerca la solucion.</span>
                    </div>
                </div>
            <?php else: ?>
                <div class="result-banner">
                    <div>
                        <h2 id="result-title"><?= htmlspecialchars($result['message'], ENT_QUOTES, 'UTF-8') ?></h2>
                        <p><?= count($result['steps']) - 1 ?> pivote<?= count($result['steps']) - 1 === 1 ? '' : 's' ?> realizado<?= count($result['steps']) - 1 === 1 ? '' : 's' ?></p>
                    </div>
                    <?php if ($result['status'] === 'optimal'): ?>
                        <div class="objective-value">Z = <?= displayNumber($result['objectiveValue']) ?></div>
                    <?php endif; ?>
                </div>

                <?php if ($result['status'] === 'optimal'): ?>
                    <div class="solution-grid">
                        <?php foreach ($result['solution'] as $index => $value): ?>
                            <div class="solution-item">
                                <span>x<?= $index + 1 ?></span>
                                <strong><?= displayNumber($value) ?></strong>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($model && count($model['objective']) === 2): ?>
                        <section class="chart-section" aria-labelledby="chart-title">
                            <div class="chart-heading">
                                <div>
                                    <span class="step-tag">Lectura visual</span>
                                    <h3 id="chart-title">Región factible y solución óptima</h3>
                                </div>
                                <span class="chart-status" id="javascript-status">Calculando con JavaScript...</span>
                            </div>
                            <div class="chart-wrap">
                                <canvas id="simplex-chart" width="760" height="430" aria-label="Gráfica de restricciones y solución óptima"></canvas>
                            </div>
                            <p class="chart-note">La gráfica representa las restricciones sobre x1 y x2. El punto marcado es la solución calculada por PHP y verificada con el simplex en JavaScript.</p>
                        </section>
                    <?php endif; ?>
                <?php endif; ?>

                <div class="steps">
                    <?php foreach ($result['steps'] as $step): ?>
                        <article class="step">
                            <h3>
                                <?= htmlspecialchars($step['title'], ENT_QUOTES, 'UTF-8') ?>
                                <?php if ($step['entering']): ?><span>Entra <?= htmlspecialchars($step['entering'], ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars($step['leaving'], ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?>
                            </h3>
                            <div class="table-wrap">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Base</th>
                                            <?php foreach ($result['columnNames'] as $columnName): ?><th><?= htmlspecialchars($columnName, ENT_QUOTES, 'UTF-8') ?></th><?php endforeach; ?>
                                            <th>RHS</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($step['tableau'] as $rowIndex => $row): ?>
                                            <tr class="<?= $rowIndex === count($step['tableau']) - 1 ? 'objective-row' : '' ?>">
                                                <td><?= $rowIndex === count($step['tableau']) - 1 ? 'Z' : htmlspecialchars($result['columnNames'][$step['basis'][$rowIndex]], ENT_QUOTES, 'UTF-8') ?></td>
                                                <?php foreach ($row as $value): ?><td><?= displayNumber($value) ?></td><?php endforeach; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </main>
    <?php if ($model): ?>
        <script>
            window.simplexModel = <?= json_encode($model, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
        </script>
    <?php endif; ?>
    <script src="index.js"></script>
</body>

</html>