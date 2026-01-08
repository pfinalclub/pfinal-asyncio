import http from 'k6/http';
import { sleep, check } from 'k6';

// 定义测试配置
export const options = {
  scenarios: {
    // 常量负载测试：每秒20个请求，持续30秒
    constant_load: {
      executor: 'constant-arrival-rate',
      rate: 20,
      timeUnit: '1s',
      duration: '30s',
      preAllocatedVUs: 10,
      maxVUs: 50,
    },
    // 渐变负载测试：从每秒10个请求增加到每秒50个请求，持续60秒
    ramp_up: {
      executor: 'ramping-arrival-rate',
      startRate: 10,
      timeUnit: '1s',
      stages: [
        { duration: '30s', target: 30 },
        { duration: '30s', target: 50 },
      ],
      preAllocatedVUs: 20,
      maxVUs: 100,
    },
    // 峰值负载测试：突发50个请求，持续10秒
    spike_load: {
      executor: 'constant-arrival-rate',
      rate: 50,
      timeUnit: '1s',
      duration: '10s',
      preAllocatedVUs: 30,
      maxVUs: 100,
      startTime: '70s', // 在前面的测试之后开始
    },
  },
  thresholds: {
    http_req_duration: ['p(95)<500'], // 95%的请求响应时间应小于500ms
    http_req_failed: ['rate<0.01'], // 请求失败率应小于1%
  },
};

// 定义测试函数
export default function () {
  // 测试不同的端点
  const endpoints = ['/', '/sleep', '/concurrent', '/context'];
  const randomEndpoint = endpoints[Math.floor(Math.random() * endpoints.length)];
  
  // 发送HTTP请求
  const res = http.get(`http://localhost:8000${randomEndpoint}`);
  
  // 检查响应
  check(res, {
    'status is 200': (r) => r.status === 200,
  });
  
  // 短暂休眠，模拟真实用户行为
  sleep(0.1);
}

// 定义测试前后的钩子
export function setup() {
  console.log('Starting pfinal-asyncio performance test...');
}

export function teardown() {
  console.log('Finished pfinal-asyncio performance test.');
}