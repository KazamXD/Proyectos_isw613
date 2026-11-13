<?php

function neutralizarFormulaCSV(string $valor): string
{
    // Detectar caracteres que podrían iniciar una fórmula
    if (preg_match('/^[\s\x00-\x1F]*[=+\-@]/u', $valor)) {
        return "'" . $valor;
    }

    return $valor;
}