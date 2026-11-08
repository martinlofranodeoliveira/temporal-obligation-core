# Temporal Obligation Core (Português)

[![CI](https://github.com/martinlofranodeoliveira/temporal-obligation-core/actions/workflows/ci.yml/badge.svg)](https://github.com/martinlofranodeoliveira/temporal-obligation-core/actions/workflows/ci.yml)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
[![PHP: 8.4+](https://img.shields.io/badge/PHP-8.4%2B-777BB4.svg?logo=php)](https://www.php.net)

Um SDK tipado e offline de contratos temporais para traços densos de eventos booleanos implementado em PHP 8.4 (64-bit). Avalia obrigações limitadas e aninhadas com explicações verificáveis, separando propriedades falsas, incerteza sobre o futuro não observado e avaliação incompleta por recursos sob semântica trivalente de Strong Kleene.

---

## Principais Recursos

- **Semântica Trivalente de Strong Kleene**: Distingue `ComputedTrue`, `ComputedFalse` e `ComputedUnknownFuture`, garantindo que amostras não observadas no futuro nunca gerem falsos positivos ou falsos negativos precipitados.
- **Operador Until Limitado Incremental ($O(b)$)**: Mantém a conjunção do prefixo esquerdo de forma incremental, eliminando varreduras quadráticas sobre janelas temporais.
- **Memoização Ultracompacta de Tritos**: Buffer binário 1D plano pré-alocado armazenando até 1 milhão de células em cerca de $1\text{ MB}$ de memória, eliminando overhead de arrays associativos do PHP.
- **Limites Estritos de Admissão**: Máximo de 128 nós na fórmula, profundidade $\le 32$, horizonte $\le 4095$, traço $\le 4096$ amostras e até 32 proposições atômicas.
- **Taxonomia de Resultados Precisa**: Separa completamente violações comprovadas (`ComputedFalse`), dados futuros não observados (`ComputedUnknownFuture`) e exaustão de orçamento (`IncompleteResources`).
- **Zero Dependências Externas**: Código puro em PHP 8.4 (`ext-json`), executando de forma 100% determinística e offline.
- **Oráculo Independente de Referência**: Testado contra oráculo em Python e enumeração exaustiva de todas as conclusões booleanas futuras.

---

## Operadores Suportados

| Operador | Sintaxe | Descrição |
| :--- | :--- | :--- |
| **Constante** | `Formula::constant(bool)` | Constante verdadeira ou falsa |
| **Átomo** | `Formula::atom(string)` | Proposição booleana nomeada |
| **Not** | `Formula::not(Formula)` | Negação em Strong Kleene |
| **And** | `Formula::and(...Formula)` | Conjunção (Falso domina) |
| **Or** | `Formula::or(...Formula)` | Disjunção (Verdadeiro domina) |
| **Next** | `Formula::next(Formula)` | Avaliação no tique $t + 1$ |
| **Always** | `Formula::always(a, b, Formula)` | Válido em todo tique no intervalo $[t + a, t + b]$ |
| **Eventually** | `Formula::eventually(a, b, Formula)` | Válido em algum tique no intervalo $[t + a, t + b]$ |
| **Until** | `Formula::until(a, b, Left, Right)` | Right ocorre em $t + j$ com Left válido em $[t, t+j-1]$ |

---

## Guia Rápido

### Uso da API PHP

```php
use Lofrano\TemporalObligation\Formula;
use Lofrano\TemporalObligation\Trace;
use Lofrano\TemporalObligation\Evaluator;

// Fórmula: Always[0, 5](request -> Eventually[1, 3](ack))
$formula = Formula::always(0, 5,
    Formula::or(
        Formula::not(Formula::atom('request')),
        Formula::eventually(1, 3, Formula::atom('ack'))
    )
);

$trace = Trace::fromSamples(['request', 'ack'], [
    ['request' => true,  'ack' => false], // tick 0
    ['request' => false, 'ack' => false], // tick 1
    ['request' => false, 'ack' => true],  // tick 2
    ['request' => false, 'ack' => false], // tick 3
]);

$evaluator = new Evaluator();
$result = $evaluator->evaluateObligations($formula, $trace, startTicks: [0]);

echo $result->overallStatus; // ComputedTrue
print_r($result->perTickResults[0]['witnessTicks']); // [2]
```

### Ferramenta CLI

```sh
# Executar cenários demonstrativos com asserções automáticas
php bin/temporal-obligation.php demo --assert

# Exibir resultados em formato JSON estruturado
php bin/temporal-obligation.php demo --json

# Avaliar entrada personalizada a partir de arquivo JSON
php bin/temporal-obligation.php run --input trace.json
```

---

## Testes e Validação

Execute a suíte de testes de forma offline com PHP 8.4:

```sh
php tests/run.php
```

Ou através do Docker:

```sh
docker run --rm -v "$(pwd)":/workspace -w /workspace php:8.4-cli-bookworm php tests/run.php
```

---

## Documentação Técnica

- [Contrato Matemático Formal e Demonstrações](docs/contract.md)
- [Arquitetura de Software e Decisões de Design](docs/design.md)

---

## Segurança

- Execução estritamente local: sem conexões de rede, subprocessos ou daemons.
- Reporte confidencial de vulnerabilidades disponível via [GitHub Security Advisories](https://github.com/martinlofranodeoliveira/temporal-obligation-core/security).
- Consulte [SECURITY.md](SECURITY.md) para detalhes.

---

## Licença

Licença MIT © 2026 Lofrano Tecnologia. Consulte [LICENSE](LICENSE) para mais detalhes.
