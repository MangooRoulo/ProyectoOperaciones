<?php
function parseNumberList(string $value): array
{
    $parts = preg_split('/\s*[,;]\s*/', trim($value));
    $numbers = [];

    foreach ($parts as $part) {
        $normalized = str_replace(',', '.', trim($part));
        if ($normalized === '' || !is_numeric($normalized)) {
            throw new InvalidArgumentException('Cada coeficiente debe ser un numero valido.');
        }
        $numbers[] = (float) $normalized;
    }

    return $numbers;
}

function cleanNumber(float $value): float
{
    return abs($value) < 0.0000001 ? 0.0 : round($value, 6);
}

function simplex(array $objective, array $constraints, array $rhs): array
{
    $variableCount = count($objective);
    $constraintCount = count($constraints);
    $columnNames = [];

    for ($index = 0; $index < $variableCount; $index++) {
        $columnNames[] = 'x' . ($index + 1);
    }
    for ($index = 0; $index < $constraintCount; $index++) {
        $columnNames[] = 's' . ($index + 1);
    }

    $tableau = [];
    foreach ($constraints as $rowIndex => $constraint) {
        $row = $constraint;
        for ($slackIndex = 0; $slackIndex < $constraintCount; $slackIndex++) {
            $row[] = $slackIndex === $rowIndex ? 1.0 : 0.0;
        }
        $row[] = $rhs[$rowIndex];
        $tableau[] = $row;
    }

    $objectiveRow = array_map(fn (float $coefficient): float => -$coefficient, $objective);
    for ($index = 0; $index < $constraintCount; $index++) {
        $objectiveRow[] = 0.0;
    }
    $objectiveRow[] = 0.0;
    $tableau[] = $objectiveRow;

    $basis = [];
    for ($index = 0; $index < $constraintCount; $index++) {
        $basis[] = $variableCount + $index;
    }

    $steps = [[
        'title' => 'Tabla inicial',
        'tableau' => $tableau,
        'basis' => $basis,
        'entering' => null,
        'leaving' => null,
    ]];

    $iteration = 0;
    while ($iteration < 100) {
        $lastRowIndex = count($tableau) - 1;
        $rhsColumn = count($tableau[0]) - 1;
        $entering = null;

        for ($column = 0; $column < $rhsColumn; $column++) {
            if ($tableau[$lastRowIndex][$column] < -0.0000001 && ($entering === null || $tableau[$lastRowIndex][$column] < $tableau[$lastRowIndex][$entering])) {
                $entering = $column;
            }
        }
        if ($entering === null) {
            break;
        }

        $leaving = null;
        $bestRatio = null;
        for ($row = 0; $row < $constraintCount; $row++) {
            if ($tableau[$row][$entering] > 0.0000001) {
                $ratio = $tableau[$row][$rhsColumn] / $tableau[$row][$entering];
                if ($bestRatio === null || $ratio < $bestRatio) {
                    $bestRatio = $ratio;
                    $leaving = $row;
                }
            }
        }

        if ($leaving === null) {
            return [
                'status' => 'unbounded',
                'message' => 'La funcion objetivo puede crecer indefinidamente con estas restricciones.',
                'columnNames' => $columnNames,
                'steps' => $steps,
                'solution' => [],
                'objectiveValue' => null,
            ];
        }

        $pivot = $tableau[$leaving][$entering];
        for ($column = 0; $column <= $rhsColumn; $column++) {
            $tableau[$leaving][$column] = cleanNumber($tableau[$leaving][$column] / $pivot);
        }
        for ($row = 0; $row <= $lastRowIndex; $row++) {
            if ($row === $leaving) {
                continue;
            }
            $factor = $tableau[$row][$entering];
            for ($column = 0; $column <= $rhsColumn; $column++) {
                $tableau[$row][$column] = cleanNumber($tableau[$row][$column] - $factor * $tableau[$leaving][$column]);
            }
        }

        $basis[$leaving] = $entering;
        $iteration++;
        $steps[] = [
            'title' => 'Iteracion ' . $iteration,
            'tableau' => $tableau,
            'basis' => $basis,
            'entering' => $columnNames[$entering],
            'leaving' => 'Fila ' . ($leaving + 1),
        ];
    }

    if ($iteration === 100) {
        return [
            'status' => 'limit',
            'message' => 'Se alcanzo el limite de iteraciones permitido.',
            'columnNames' => $columnNames,
            'steps' => $steps,
            'solution' => [],
            'objectiveValue' => null,
        ];
    }

    $solution = array_fill(0, $variableCount, 0.0);
    $rhsColumn = count($tableau[0]) - 1;
    foreach ($basis as $row => $basicColumn) {
        if ($basicColumn < $variableCount) {
            $solution[$basicColumn] = cleanNumber($tableau[$row][$rhsColumn]);
        }
    }

    return [
        'status' => 'optimal',
        'message' => 'Se encontro una solucion optima.',
        'columnNames' => $columnNames,
        'steps' => $steps,
        'solution' => $solution,
        'objectiveValue' => cleanNumber($tableau[count($tableau) - 1][$rhsColumn]),
    ];
}
