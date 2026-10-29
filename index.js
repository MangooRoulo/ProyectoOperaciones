// Configura el modo estricto y las referencias principales del formulario.
'use strict';

// Define la tolerancia numerica y conecta los controles de la interfaz.
const EPSILON = 0.0000001;
const constraintsContainer = document.getElementById('constraints-container');
const addConstraintButton = document.getElementById('add-constraint');
const generateModelButton = document.getElementById('generate-model');
const variableCountInput = document.getElementById('variable-count');
const constraintCountInput = document.getElementById('constraint-count');

// Limpia los resultados numericos para evitar errores de redondeo visibles.
function cleanNumber(value) {
    return Math.abs(value) < EPSILON ? 0 : Number(value.toFixed(6));
}

// Resuelve el modelo de programacion lineal mediante el metodo Simplex.
function simplex(objective, constraints, rhs) {
    const variableCount = objective.length;
    const constraintCount = constraints.length;
    const columnNames = [
        ...objective.map((_, index) => `x${index + 1}`),
        ...constraints.map((_, index) => `s${index + 1}`),
    ];
    const tableau = constraints.map((constraint, rowIndex) => [
        ...constraint,
        ...constraints.map((_, slackIndex) => (slackIndex === rowIndex ? 1 : 0)),
        rhs[rowIndex],
    ]);

    tableau.push([
        ...objective.map((coefficient) => -coefficient),
        ...constraints.map(() => 0),
        0,
    ]);

    const basis = constraints.map((_, index) => variableCount + index);
    const steps = [];
    const snapshot = (title, entering = null, leaving = null) => {
        steps.push({
            title,
            tableau: tableau.map((row) => row.slice()),
            basis: basis.slice(),
            entering,
            leaving,
        });
    };

    snapshot('Tabla inicial');
    let iteration = 0;

    while (iteration < 100) {
        const objectiveRow = tableau.length - 1;
        const rhsColumn = tableau[0].length - 1;
        let entering = null;

        for (let column = 0; column < rhsColumn; column += 1) {
            if (
                tableau[objectiveRow][column] < -EPSILON &&
                (entering === null ||
                    tableau[objectiveRow][column] < tableau[objectiveRow][entering])
            ) {
                entering = column;
            }
        }
        if (entering === null) break;

        let leaving = null;
        let bestRatio = null;
        for (let row = 0; row < constraintCount; row += 1) {
            if (tableau[row][entering] > EPSILON) {
                const ratio = tableau[row][rhsColumn] / tableau[row][entering];
                if (bestRatio === null || ratio < bestRatio) {
                    bestRatio = ratio;
                    leaving = row;
                }
            }
        }

        if (leaving === null) {
            return {
                status: 'unbounded',
                message: 'La funcion objetivo puede crecer indefinidamente con estas restricciones.',
                columnNames,
                steps,
                solution: [],
                objectiveValue: null,
            };
        }

        const pivot = tableau[leaving][entering];
        tableau[leaving] = tableau[leaving].map((value) => cleanNumber(value / pivot));
        for (let row = 0; row <= objectiveRow; row += 1) {
            if (row === leaving) continue;
            const factor = tableau[row][entering];
            tableau[row] = tableau[row].map((value, column) =>
                cleanNumber(value - factor * tableau[leaving][column]),
            );
        }

        basis[leaving] = entering;
        iteration += 1;
        snapshot(`Iteracion ${iteration}`, columnNames[entering], `Fila ${leaving + 1}`);
    }

    if (iteration === 100) {
        return {
            status: 'limit',
            message: 'Se alcanzo el limite de iteraciones permitido.',
            columnNames,
            steps,
            solution: [],
            objectiveValue: null,
        };
    }

    const rhsColumn = tableau[0].length - 1;
    const solution = Array(variableCount).fill(0);
    basis.forEach((basicColumn, row) => {
        if (basicColumn < variableCount) {
            solution[basicColumn] = cleanNumber(tableau[row][rhsColumn]);
        }
    });

    return {
        status: 'optimal',
        message: 'Se encontro una solucion optima.',
        solution,
        objectiveValue: cleanNumber(tableau[tableau.length - 1][rhsColumn]),
        columnNames,
        steps,
    };
}

// Formatea los numeros que se muestran en las tablas y la grafica.
function displayNumber(value) {
    return String(Number(value.toFixed(4)));
}

// Construye en el navegador las tablas de cada iteracion del Simplex.
function renderSteps(result, container) {
    container.innerHTML = '';
    result.steps.forEach((step) => {
        const article = document.createElement('article');
        article.className = 'step';
        const title = document.createElement('h3');
        title.textContent = step.title;
        if (step.entering) {
            const detail = document.createElement('span');
            detail.textContent = `Entra ${step.entering} · ${step.leaving}`;
            title.appendChild(detail);
        }

        const wrap = document.createElement('div');
        wrap.className = 'table-wrap';
        const table = document.createElement('table');
        const header = ['Base', ...result.columnNames, 'RHS'];
        const thead = document.createElement('thead');
        const headerRow = document.createElement('tr');
        header.forEach((name) => {
            const cell = document.createElement('th');
            cell.textContent = name;
            headerRow.appendChild(cell);
        });
        thead.appendChild(headerRow);
        table.appendChild(thead);

        const body = document.createElement('tbody');
        step.tableau.forEach((row, rowIndex) => {
            const tr = document.createElement('tr');
            if (rowIndex === step.tableau.length - 1) tr.className = 'objective-row';
            const base = rowIndex === step.tableau.length - 1
                ? 'Z'
                : result.columnNames[step.basis[rowIndex]];
            [base, ...row.map(displayNumber)].forEach((value) => {
                const cell = document.createElement('td');
                cell.textContent = value;
                tr.appendChild(cell);
            });
            body.appendChild(tr);
        });
        table.appendChild(body);
        wrap.appendChild(table);
        article.append(title, wrap);
        container.appendChild(article);
    });
}

// Activa los botones que eliminan filas de restricciones.
function bindRemoveButtons() {
    document.querySelectorAll('.remove-constraint').forEach((button) => {
        button.onclick = () => {
            if (constraintsContainer.children.length > 1) {
                button.closest('.constraint-row').remove();
                renumberConstraints();
            }
        };
    });
}

// Activa las ayudas contextuales de los campos del formulario.
function bindHelpButtons() {
    document.querySelectorAll('.help-toggle').forEach((button) => {
        button.onclick = () => {
            const isOpen = button.getAttribute('aria-expanded') === 'true';
            document.querySelectorAll('.help-toggle').forEach((otherButton) => {
                otherButton.setAttribute('aria-expanded', 'false');
                const oldHelp = otherButton.parentElement.querySelector('.help-popover');
                if (oldHelp) oldHelp.remove();
            });
            if (!isOpen) {
                const help = document.createElement('span');
                help.className = 'help-popover';
                help.textContent = button.dataset.help;
                button.parentElement.appendChild(help);
                button.setAttribute('aria-expanded', 'true');
            }
        };
    });
}

// Mantiene ordenados los nombres e indices de las restricciones.
function renumberConstraints() {
    [...constraintsContainer.children].forEach((row, index) => {
        row.querySelector('.constraint-number').textContent = `R${index + 1}`;
        row.querySelectorAll('input').forEach((input, inputIndex) => {
            const fieldName = inputIndex === 0 ? 'coefficients' : 'rhs';
            input.name = `constraints[${index}][${fieldName}]`;
            input.setAttribute('aria-label', `${fieldName} de restriccion ${index + 1}`);
        });
    });
}

// Actualiza los ejemplos de entrada segun la cantidad de variables.
function updateModelExamples() {
    const variableCount = Number(variableCountInput.value) || 2;
    const example = Array.from({ length: variableCount }, (_, index) => index + 1).join(', ');
    document.getElementById('objective').placeholder = `Ej. ${example}`;
    document.querySelectorAll('[name$="[coefficients]"]').forEach((input) => {
        input.placeholder = `Ej. ${example}`;
    });
}

// Crea una nueva fila editable para una restriccion.
function addConstraintRow() {
    const index = constraintsContainer.children.length;
    const row = document.createElement('div');
    row.className = 'constraint-row';
    row.innerHTML = `
        <div class="constraint-number">R${index + 1}</div>
        <div class="field"><div class="field-label-line"><span class="field-label">Coeficientes</span><button type="button" class="help-toggle" aria-label="Ayuda para coeficientes" aria-expanded="false" data-help="Escribe un número por variable. Ejemplo: 1, 2 representa 1x1 + 2x2.">!</button></div><input name="constraints[${index}][coefficients]" placeholder="Ej. 1, 2" required aria-label="Coeficientes de restriccion ${index + 1}"></div>
        <div class="field"><div class="field-label-line"><span class="field-label">Límite RHS</span><button type="button" class="help-toggle" aria-label="Ayuda para el límite RHS" aria-expanded="false" data-help="Escribe el límite de la restricción. Ejemplo: 10 significa que la suma no puede superar 10.">!</button></div><input name="constraints[${index}][rhs]" placeholder="Ej. 10" required aria-label="Lado derecho de restriccion ${index + 1}"></div>
        <button type="button" class="icon-button remove-constraint" title="Eliminar restriccion" aria-label="Eliminar restriccion">×</button>`;
    constraintsContainer.appendChild(row);
    bindRemoveButtons();
    bindHelpButtons();
}

// Controla la adicion manual de restricciones hasta el limite permitido.
addConstraintButton.addEventListener('click', () => {
    if (constraintsContainer.children.length < 8) addConstraintRow();
});

// Regenera todas las filas cuando cambia el tamano del modelo.
generateModelButton.addEventListener('click', () => {
    const count = Math.min(8, Math.max(1, Number(constraintCountInput.value) || 1));
    constraintCountInput.value = count;
    constraintsContainer.innerHTML = '';
    for (let index = 0; index < count; index += 1) addConstraintRow();
    updateModelExamples();
});

// Inicializa los comportamientos de los elementos entregados por PHP.
bindRemoveButtons();
bindHelpButtons();
variableCountInput.addEventListener('change', updateModelExamples);
updateModelExamples();

// Calcula los vertices de la region factible para dos variables.
function feasibleVertices(constraints, rhs) {
    const lines = [
        ...constraints.map((constraint, index) => [constraint[0], constraint[1], rhs[index]]),
        [1, 0, 0],
        [0, 1, 0],
    ];
    const points = [];
    for (let first = 0; first < lines.length; first += 1) {
        for (let second = first + 1; second < lines.length; second += 1) {
            const [a1, b1, c1] = lines[first];
            const [a2, b2, c2] = lines[second];
            const determinant = a1 * b2 - a2 * b1;
            if (Math.abs(determinant) < EPSILON) continue;
            const point = [(c1 * b2 - c2 * b1) / determinant, (a1 * c2 - a2 * c1) / determinant];
            const isFeasible = point[0] >= -EPSILON && point[1] >= -EPSILON &&
                constraints.every((constraint, index) =>
                    constraint[0] * point[0] + constraint[1] * point[1] <= rhs[index] + EPSILON);
            if (isFeasible && !points.some((other) =>
                Math.abs(other[0] - point[0]) < EPSILON && Math.abs(other[1] - point[1]) < EPSILON)) {
                points.push(point);
            }
        }
    }
    return points;
}

// Recorta una recta para que pueda dibujarse dentro del plano visible.
function clipLine(a, b, c, maxX, maxY) {
    const candidates = [];
    if (Math.abs(b) > EPSILON) candidates.push([0, c / b], [maxX, (c - a * maxX) / b]);
    if (Math.abs(a) > EPSILON) candidates.push([c / a, 0], [(c - b * maxY) / a, maxY]);
    return candidates.filter(([x, y]) =>
        x >= -EPSILON && y >= -EPSILON && x <= maxX + EPSILON && y <= maxY + EPSILON)
        .filter((point, index, all) => all.findIndex((other) =>
            Math.abs(other[0] - point[0]) < EPSILON && Math.abs(other[1] - point[1]) < EPSILON) === index)
        .slice(0, 2);
}

// Dibuja la region factible, las restricciones y la solucion optima.
function drawSimplexChart(model, result) {
    const canvas = document.getElementById('simplex-chart');
    if (!canvas || model.objective.length !== 2 || result.status !== 'optimal') return;
    const context = canvas.getContext('2d');
    const width = canvas.width;
    const height = canvas.height;
    const padding = { left: 58, right: 24, top: 22, bottom: 48 };
    const vertices = feasibleVertices(model.constraints, model.rhs);
    const valuesX = [1, result.solution[0], ...vertices.map((point) => point[0])];
    const valuesY = [1, result.solution[1], ...vertices.map((point) => point[1])];
    model.constraints.forEach((constraint, index) => {
        if (constraint[0] > 0) valuesX.push(model.rhs[index] / constraint[0]);
        if (constraint[1] > 0) valuesY.push(model.rhs[index] / constraint[1]);
    });
    const maxX = Math.max(...valuesX, 1) * 1.12;
    const maxY = Math.max(...valuesY, 1) * 1.12;
    const x = (value) => padding.left + (value / maxX) * (width - padding.left - padding.right);
    const y = (value) => height - padding.bottom - (value / maxY) * (height - padding.top - padding.bottom);

    context.clearRect(0, 0, width, height);
    context.fillStyle = '#fbfcfa';
    context.fillRect(0, 0, width, height);
    context.strokeStyle = '#dfe5df';
    context.lineWidth = 1;
    for (let tick = 0; tick <= 5; tick += 1) {
        const xValue = maxX * tick / 5;
        const yValue = maxY * tick / 5;
        context.beginPath();
        context.moveTo(x(xValue), y(0));
        context.lineTo(x(xValue), y(maxY));
        context.moveTo(x(0), y(yValue));
        context.lineTo(x(maxX), y(yValue));
        context.stroke();
        context.fillStyle = '#4b5560';
        context.font = '12px Trebuchet MS';
        context.textAlign = 'center';
        context.fillText(Number(xValue.toFixed(2)), x(xValue), y(0) + 18);
        context.textAlign = 'right';
        context.fillText(Number(yValue.toFixed(2)), x(0) - 8, y(yValue) + 4);
    }

    if (vertices.length > 2) {
        const center = vertices.reduce((sum, point) => [sum[0] + point[0], sum[1] + point[1]], [0, 0])
            .map((value) => value / vertices.length);
        vertices.sort((a, b) => Math.atan2(a[1] - center[1], a[0] - center[0]) -
            Math.atan2(b[1] - center[1], b[0] - center[0]));
        context.fillStyle = 'rgba(13, 124, 120, 0.12)';
        context.beginPath();
        vertices.forEach((point, index) => index ? context.lineTo(x(point[0]), y(point[1])) : context.moveTo(x(point[0]), y(point[1])));
        context.closePath();
        context.fill();
    }

    context.strokeStyle = '#17202a';
    context.lineWidth = 2;
    context.beginPath();
    context.moveTo(x(0), y(0));
    context.lineTo(x(maxX), y(0));
    context.moveTo(x(0), y(0));
    context.lineTo(x(0), y(maxY));
    context.stroke();
    const colors = ['#0d7c78', '#ef765f', '#d39b16', '#4976a8'];
    model.constraints.forEach((constraint, index) => {
        const ends = clipLine(constraint[0], constraint[1], model.rhs[index], maxX, maxY);
        if (ends.length < 2) return;
        context.strokeStyle = colors[index % colors.length];
        context.beginPath();
        context.moveTo(x(ends[0][0]), y(ends[0][1]));
        context.lineTo(x(ends[1][0]), y(ends[1][1]));
        context.stroke();
    });

    const [solutionX, solutionY] = result.solution;
    context.fillStyle = '#07534f';
    context.beginPath();
    context.arc(x(solutionX), y(solutionY), 7, 0, Math.PI * 2);
    context.fill();
    context.fillStyle = '#17202a';
    context.font = 'bold 13px Trebuchet MS';
    context.textAlign = 'left';
    context.fillText(`Optimo (${displayNumber(solutionX)}, ${displayNumber(solutionY)})`, x(solutionX) + 10, y(solutionY) - 10);
    context.font = '12px Trebuchet MS';
    context.fillText('x1', width - padding.right - 10, y(0) + 28);
    context.fillText('x2', x(0) - 25, padding.top + 4);
}

// Ejecuta el calculo JavaScript y renderiza la grafica del resultado PHP.
if (window.simplexModel) {
    const result = simplex(window.simplexModel.objective, window.simplexModel.constraints, window.simplexModel.rhs);
    const status = document.getElementById('javascript-status');
    if (status) {
        status.textContent = result.status === 'optimal'
            ? `JavaScript: Z = ${displayNumber(result.objectiveValue)}`
            : `JavaScript: ${result.status}`;
    }
    drawSimplexChart(window.simplexModel, result);
}
