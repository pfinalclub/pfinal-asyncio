# 性能基准测试

完整的性能基准测试套件，用于评估 PHP AsyncIO 的性能表现。

## 📊 测试项目

| 测试 | 文件 | 说明 |
|------|------|------|
| 1 | `01_task_creation.php` | 任务创建开销测试 |
| 2 | `02_concurrent_tasks.php` | 并发任务性能测试 |
| 3 | `03_context_switch.php` | 上下文切换延迟测试 |
| 4 | `04_memory_usage.php` | 内存使用分析 |
| 5 | `05_real_world.php` | 真实场景模拟 |

## 🚀 运行测试

### 运行单个测试

```bash
php benchmarks/01_task_creation.php
```

### 运行所有测试

```bash
php benchmarks/run_all.php
```

### 通过 Composer

```bash
composer benchmark
```

## 📈 测试指标

每个测试会输出以下指标：

- **平均耗时**: 每次操作的平均时间（毫秒）
- **最小/最大耗时**: 最快和最慢的执行时间
- **标准差**: 性能稳定性指标
- **内存使用**: 平均内存占用（KB）
- **吞吐量**: 每秒操作数（ops/s）

## 📁 报告输出

测试报告保存在 `benchmarks/reports/` 目录：

```
benchmarks/reports/
├── 01_task_creation.txt
├── 02_concurrent_tasks.txt
├── 03_context_switch.txt
├── 04_memory_usage.txt
├── 05_real_world.txt
└── summary.txt
```

## 🎯 性能目标

根据 ROADMAP 的规划，我们的性能目标：

| 指标 | 目标 | 说明 |
|------|------|------|
| 任务创建 | < 0.1ms/任务 | 创建单个任务的开销 |
| 上下文切换 | < 0.05ms | Fiber suspend/resume 的开销 |
| 内存占用 | < 100KB/1000任务 | 内存效率 |
| 并发吞吐 | > 10000 ops/s | 并发处理能力 |

## 📊 性能对比

与其他异步库对比（计划中）：

```bash
php benchmarks/06_compare_with_others.php
```

将对比：
- 原生回调
- ReactPHP
- Swoole（如果可用）

## 🔧 自定义测试

### 基本结构

```php
<?php
require_once __DIR__ . '/BenchmarkRunner.php';

use PfinalClub\Asyncio\Benchmarks\BenchmarkRunner;

$runner = new BenchmarkRunner(
    warmupRounds: 3,  // 预热轮数
    testRounds: 10    // 测试轮数
);

// 添加测试
$runner->run("测试名称", function() {
    // 你的测试代码
});

// 生成报告
echo $runner->generateReport();
$runner->saveReport('reports/my_test.txt');
```

### 测试最佳实践

1. **预热**: 使用足够的预热轮数（至少 3 轮）
2. **重复**: 测试轮数不少于 10 轮
3. **隔离**: 每次测试后清理内存
4. **稳定**: 确保测试环境稳定（关闭其他程序）

## 📝 添加新测试

1. 创建测试文件：`benchmarks/06_my_test.php`
2. 使用 `BenchmarkRunner` 类
3. 添加到 `run_all.php`
4. 更新此 README

## 🐛 问题排查

### 测试失败

如果测试失败，检查：

1. PHP 版本 >= 8.1（需要 Fiber 支持）
2. Workerman 已安装
3. 内存限制足够（建议 512MB+）
4. 没有其他进程占用资源

### 结果异常

如果结果异常（如性能极差）：

1. 检查 CPU 占用
2. 检查内存使用
3. 关闭调试模式
4. 清理缓存：`composer dump-autoload`

## 📚 参考

- [ROADMAP.md](../ROADMAP.md) - 性能目标和计划
- [PHP Performance Tips](https://www.php.net/manual/en/features.gc.performance-considerations.php)
- [Benchmarking Best Practices](https://github.com/php/php-src/blob/master/CODING_STANDARDS.md)

---

**提示**: 性能测试结果受多种因素影响（硬件、PHP 配置、系统负载等），
建议在相同环境下多次运行取平均值。

