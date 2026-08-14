# Generics benchmark results

Branch `generics` against its merge base with master, both built `--disable-debug --enable-opcache`.
Metric is Callgrind instruction count (Ir) of a single request, measured through
`php-cgi -T`: **cold** is one request including compilation, **warm** is one request
in steady state after 5 warmup requests have filled opcache and the JIT.
Generated 2026-08-13.

## 1. What the branch costs code that uses no generics

Change in Ir from the merge-base build to the generics build, running identical
generics-free code. `Zend/bench.php` is php-src's own benchmark; php-parser parses
its own source tree; the other two are the collection pipelines below written
without generics.

| config | Zend/bench.php | php-parser | collections | doctrine/collections |
| :--- | ---: | ---: | ---: | ---: |
| opcache off | +0.01% | +1.43% | +1.74% | +1.67% |
| JIT off, cold | +0.02% | +1.50% | +1.92% | +1.82% |
| JIT off, warm | +0.00% | +1.77% | +1.77% | +1.71% |
| tracing JIT, cold | +0.10% | +1.15% | +2.08% | +2.11% |
| tracing JIT, warm | +0.00% | +2.76% | +1.91% | +2.00% |
| function JIT, warm | +0.00% | +1.83% | +1.89% | +1.88% |

## 2. What using generics costs

**vs plain** compares the generic version of a workload against the hand-written
non-generic version of the same workload on the same build — the marginal cost of
the generics. **vs base PHP** compares it against the non-generic version on the
merge-base build — the total an application pays, tax included.

| config | collections<br>vs plain | collections<br>vs base PHP | doctrine<br>vs plain | doctrine<br>vs base PHP |
| :--- | ---: | ---: | ---: | ---: |
| opcache off | +0.11% | +1.85% | +0.02% | +1.69% |
| JIT off, cold | +0.00% | +1.92% | -0.04% | +1.78% |
| JIT off, warm | +0.00% | +1.77% | -0.05% | +1.66% |
| tracing JIT, cold | +0.17% | +2.26% | +3.95% | +6.14% |
| tracing JIT, warm | +0.23% | +2.15% | +8.07% | +10.24% |
| function JIT, warm | +0.80% | +2.71% | +2.87% | +4.81% |

## 3. What preloading the instantiations saves

An instantiation is built by cloning the template, and the result lives in the
per-request arena — so a warm opcache does not amortise it the way it does an
ordinary class, and every request rebuilds it. Preloading is the exception: it
stamps the closed world once, at startup, into shared memory.

Change from preloading the collections workload's instantiations; negative is
cheaper preloaded.

| config | change |
| :--- | ---: |
| JIT off, cold | +0.20% |
| JIT off, warm | +0.08% |
| tracing JIT, cold | +1.83% |
| tracing JIT, warm | +2.49% |
| function JIT, warm | +0.09% |

That workload stamps three instantiations per request, far too few to pay for
preloading's own overhead. Under tracing JIT it costs more still: preloaded
generic families are kept interpreted on this branch, so preloading trades away
JIT coverage.

Stamping 200 instantiations per request instead, where the saving is the
dominant term:

| 200 instantiations per request | change |
| :--- | ---: |
| instructions | -80.30% |
| memory | -77.82% |

Preloading is worth whatever a request would otherwise spend rebuilding
instantiations: nothing for a handful, most of the cost for hundreds.

## 4. What one more instantiation costs

The marginal cost of an instantiation, from the difference between stamping a
given number of them and stamping none — so loading the library and declaring the
type-argument classes cancels out. Both columns are per request: without
preloading, this is paid again on every one.

| instantiations | instructions each | bytes each |
| :--- | ---: | ---: |
| 100 | 8,499 | 3,443 |
| 200 | 8,518 | 3,768 |
| 400 | 8,505 | 3,611 |
