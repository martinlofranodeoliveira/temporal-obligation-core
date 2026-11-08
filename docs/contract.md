# Formal Contract & Semantics

## 1. Mathematical Domain

Let $\mathbb{T} = \{ \text{TRUE}, \text{FALSE}, \text{UNKNOWN\_FUTURE} \}$ denote the Strong Kleene three-valued truth domain.
Let $\sigma = (s_0, s_1, \dots, s_{N-1})$ be a dense event trace of length $N \in [0, 4096]$, where each sample $s_t \in \{0, 1\}^M$ assigns a Boolean value to each of up to $M \le 32$ declared atomic propositions.

For any atomic proposition $p$ and integer tick $t \ge 0$:
$$
\text{eval}(p, t) = \begin{cases}
\text{TRUE} & \text{if } 0 \le t < N \text{ and } p \in s_t \\
\text{FALSE} & \text{if } 0 \le t < N \text{ and } p \notin s_t \\
\text{UNKNOWN\_FUTURE} & \text{if } t \ge N
\end{cases}
$$

## 2. Strong Kleene Truth Tables

### 2.1 Negation ($\neg$)
| $x$ | $\neg x$ |
| :---: | :---: |
| TRUE | FALSE |
| FALSE | TRUE |
| UNKNOWN\_FUTURE | UNKNOWN\_FUTURE |

### 2.2 Conjunction ($\land$)
| $\land$ | TRUE | FALSE | UNKNOWN\_FUTURE |
| :---: | :---: | :---: | :---: |
| **TRUE** | TRUE | FALSE | UNKNOWN\_FUTURE |
| **FALSE** | FALSE | FALSE | FALSE |
| **UNKNOWN\_FUTURE** | UNKNOWN\_FUTURE | FALSE | UNKNOWN\_FUTURE |

*Property:* FALSE dominates conjunction unconditionally: $\text{FALSE} \land x = \text{FALSE}$ for all $x \in \mathbb{T}$.

### 2.3 Disjunction ($\lor$)
| $\lor$ | TRUE | FALSE | UNKNOWN\_FUTURE |
| :---: | :---: | :---: | :---: |
| **TRUE** | TRUE | TRUE | TRUE |
| **FALSE** | TRUE | FALSE | UNKNOWN\_FUTURE |
| **UNKNOWN\_FUTURE** | TRUE | UNKNOWN\_FUTURE | UNKNOWN\_FUTURE |

*Property:* TRUE dominates disjunction unconditionally: $\text{TRUE} \lor x = \text{TRUE}$ for all $x \in \mathbb{T}$.

---

## 3. Bounded Temporal Operators

Given interval $[a, b]$ with $0 \le a \le b \le 4095$ and start tick $t \ge 0$:

### 3.1 Next
$$
\text{eval}(\text{Next}(\phi), t) = \text{eval}(\phi, t + 1)
$$

### 3.2 Always ($\square_{[a, b]}$)
$$
\text{eval}(\square_{[a, b]} \phi, t) = \bigwedge_{k=a}^b \text{eval}(\phi, t + k)
$$
- If any $k \in [a, b]$ has $\text{eval}(\phi, t + k) = \text{FALSE}$, the entire formula evaluates to $\text{FALSE}$ with witness tick $t + k$.
- If all $k \in [a, b]$ evaluate to $\text{TRUE}$, the formula evaluates to $\text{TRUE}$.
- Otherwise, it evaluates to $\text{UNKNOWN\_FUTURE}$.

### 3.3 Eventually ($\lozenge_{[a, b]}$)
$$
\text{eval}(\lozenge_{[a, b]} \phi, t) = \bigvee_{k=a}^b \text{eval}(\phi, t + k)
$$
- If any $k \in [a, b]$ has $\text{eval}(\phi, t + k) = \text{TRUE}$, the formula evaluates to $\text{TRUE}$ with witness tick $t + k$.
- If all $k \in [a, b]$ evaluate to $\text{FALSE}$, the formula evaluates to $\text{FALSE}$.
- Otherwise, it evaluates to $\text{UNKNOWN\_FUTURE}$.

### 3.4 Bounded Until ($\text{Until}_{[a, b]}(\phi, \psi)$)
Candidate witness positions for $\psi$ range from $t + a$ to $t + b$. For candidate $j \in [a, b]$, the left operand $\phi$ must hold at all ticks from $t$ through $t + j - 1$:
$$
\text{eval}(\text{Until}_{[a, b]}(\phi, \psi), t) = \bigvee_{j=a}^b \left( \text{eval}(\psi, t + j) \land \bigwedge_{k=0}^{j-1} \text{eval}(\phi, t + k) \right)
$$

#### Incremental Prefix Maintenance ($O(b)$)
Let $P(j) = \bigwedge_{k=0}^{j-1} \text{eval}(\phi, t + k)$.
- $P(0) = \text{TRUE}$.
- For $j \ge 1$: $P(j) = P(j - 1) \land \text{eval}(\phi, t + j - 1)$.
- If $P(j) = \text{FALSE}$, then for all $m \ge j$, $P(m) = \text{FALSE}$.
- Once $P(j) = \text{FALSE}$, no subsequent candidate in $[a, b]$ can satisfy the conjunction. The evaluation can immediately terminate without evaluating remaining candidates or rescanning left prefixes.

---

## 4. Complexity & Resource Guarantees

1. **DAG Capacity**: At most 128 formula nodes, depth $\le 32$, horizon $\le 4095$.
2. **Trace Capacity**: At most 4096 samples, up to 32 named atomic flags.
3. **Memoization Bound**:
   $$\text{Nodes} \times (\text{Length} + \text{Horizon} + 2) \le 128 \times 8193 = 1,048,704 \text{ bytes} \approx 1.05\text{ MB}$$
4. **Operation Budget**: Configurable (default $1,000,000$). Any exhaustion triggers `IncompleteResources`.
