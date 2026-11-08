#!/usr/bin/env python3
"""Independent reference oracle for Strong Kleene bounded temporal logic."""
import itertools
import json
import sys

# Trits
TRUE = 1
FALSE = 2
UNKNOWN_FUTURE = 3

def kleene_not(t):
    if t == TRUE: return FALSE
    if t == FALSE: return TRUE
    return UNKNOWN_FUTURE

def kleene_and(a, b):
    if a == FALSE or b == FALSE: return FALSE
    if a == TRUE and b == TRUE: return TRUE
    return UNKNOWN_FUTURE

def kleene_or(a, b):
    if a == TRUE or b == TRUE: return TRUE
    if a == FALSE and b == FALSE: return FALSE
    return UNKNOWN_FUTURE

def eval_formula_recursive(node, trace_dict, tick, trace_len):
    """Slow recursive Strong Kleene evaluator."""
    kind = node['kind']
    if kind == 'constant':
        return TRUE if node['value'] else FALSE
    if kind == 'atom':
        name = node['name']
        if tick >= trace_len:
            return UNKNOWN_FUTURE
        samples = trace_dict.get('samples', [])
        if tick < len(samples):
            return TRUE if samples[tick].get(name, False) else FALSE
        masks = trace_dict.get('masks', [])
        if tick < len(masks):
            atoms = trace_dict['atoms']
            bit = atoms.index(name)
            return TRUE if (masks[tick] & (1 << bit)) != 0 else FALSE
        return UNKNOWN_FUTURE
    if kind == 'not':
        return kleene_not(eval_formula_recursive(node['child'], trace_dict, tick, trace_len))
    if kind == 'and':
        res = TRUE
        for child in node['children']:
            res = kleene_and(res, eval_formula_recursive(child, trace_dict, tick, trace_len))
            if res == FALSE:
                return FALSE
        return res
    if kind == 'or':
        res = FALSE
        for child in node['children']:
            res = kleene_or(res, eval_formula_recursive(child, trace_dict, tick, trace_len))
            if res == TRUE:
                return TRUE
        return res
    if kind == 'next':
        return eval_formula_recursive(node['child'], trace_dict, tick + 1, trace_len)
    if kind == 'always':
        a, b = node['a'], node['b']
        res = TRUE
        for k in range(a, b + 1):
            res = kleene_and(res, eval_formula_recursive(node['child'], trace_dict, tick + k, trace_len))
            if res == FALSE:
                return FALSE
        return res
    if kind == 'eventually':
        a, b = node['a'], node['b']
        res = FALSE
        for k in range(a, b + 1):
            res = kleene_or(res, eval_formula_recursive(node['child'], trace_dict, tick + k, trace_len))
            if res == TRUE:
                return TRUE
        return res
    if kind == 'until':
        a, b = node['a'], node['b']
        overall = FALSE
        left_prefix = TRUE
        for j in range(0, b + 1):
            if j > 0:
                l_val = eval_formula_recursive(node['left'], trace_dict, tick + j - 1, trace_len)
                left_prefix = kleene_and(left_prefix, l_val)
            if left_prefix == FALSE:
                return FALSE
            if j >= a:
                r_val = eval_formula_recursive(node['right'], trace_dict, tick + j, trace_len)
                term = kleene_and(left_prefix, r_val)
                overall = kleene_or(overall, term)
                if overall == TRUE:
                    return TRUE
        return overall
    raise ValueError(f"Unknown kind {kind}")

def enumerate_future_completions(formula, trace_dict, start_tick, future_ticks):
    """Enumerate all 2^(future_ticks * num_atoms) future completions and test consistency."""
    atoms = trace_dict['atoms']
    trace_len = len(trace_dict.get('samples', trace_dict.get('masks', [])))
    kleene_result = eval_formula_recursive(formula, trace_dict, start_tick, trace_len)
    
    # Enumerate all possible truth assignments for the future ticks
    num_vars = len(atoms) * future_ticks
    completions_results = []
    
    for combo in itertools.product([False, True], repeat=num_vars):
        extended_samples = [dict(s) for s in trace_dict.get('samples', [])]
        idx = 0
        for f_idx in range(future_ticks):
            sample = {}
            for atom in atoms:
                sample[atom] = combo[idx]
                idx += 1
            extended_samples.append(sample)
        extended_trace = {'atoms': atoms, 'samples': extended_samples}
        extended_len = len(extended_samples)
        b_res = eval_formula_recursive(formula, extended_trace, start_tick, extended_len)
        completions_results.append(b_res)

    all_true = all(r == TRUE for r in completions_results)
    all_false = all(r == FALSE for r in completions_results)
    
    return {
        'kleene_result': kleene_result,
        'all_true': all_true,
        'all_false': all_false,
        'total_completions': len(completions_results),
        'consistent': (
            (kleene_result == TRUE and all_true) or
            (kleene_result == FALSE and all_false) or
            (kleene_result == UNKNOWN_FUTURE) # Strong Kleene is conservative, cannot claim false when true exists
        )
    }

def main():
    if len(sys.argv) < 2:
        print("Usage: oracle.py <test-case.json>")
        sys.exit(1)
    with open(sys.argv[1]) as f:
        data = json.load(f)
    formula = data['formula']
    trace = data['trace']
    ticks = data['startTicks']
    trace_len = len(trace.get('samples', trace.get('masks', [])))
    results = {}
    for t in ticks:
        trit = eval_formula_recursive(formula, trace, t, trace_len)
        results[t] = trit
    print(json.dumps(results))

if __name__ == '__main__':
    main()
