# 新年倒计时网站

一个简洁美观的新年倒计时网站，支持用户留言和敏感词过滤功能。

## 功能特点

- 🎉 实时新年倒计时显示
- 💬 用户留言板功能
- 🔒 敏感词过滤和URL检测
- 🎨 响应式设计，适配各种设备
- 📱 简洁美观的用户界面

## 技术栈

- **后端**：PHP 8.2+
- **前端**：HTML5, CSS3, JavaScript
- **数据库**：MySQL
- **其他**：Vite (前端构建工具)

## 安装指南

### 前提条件

- PHP 8.2+ 运行环境
- MySQL 数据库
- Web服务器（Apache/Nginx）

### 安装步骤

1. **克隆仓库**
   ```bash
   git clone https://github.com/your-username/newyear-countdown.git
   cd newyear-countdown
   ```

2. **配置数据库**
   - 创建MySQL数据库
   - 导入数据库表结构（见下面的SQL示例）
   - 修改 `index.php` 文件中的数据库连接信息

   ```php
   // 数据库连接配置
   $servername = "localhost";
   $username = "root";
   $password = "password";
   $dbname = "newyear_messages";
   ```
   - 此项目中默认的MySQL数据库账号密码配置如下：

   ```php
   // 数据库连接配置
   $servername = "localhost";
   $username = "happynewyear";
   $password = "happynewyear0324";
   $dbname = "happynewyear";
   ```
   - 您可以自由修改数据库账密配置，确保安全！
   
2. **设置Web服务器**
   - 将项目部署到Web服务器根目录或子目录
   - 确保PHP有权限写入会话文件

3. **启动服务**
   - 可使用PHP内置服务器进行开发测试
   ```bash
   php -S localhost:8000 -t /path/to/project
   ```

## 数据库结构

```sql
CREATE TABLE messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

## 使用说明

1. **访问网站**
   - 在浏览器中输入服务器地址访问网站
   - 查看实时新年倒计时

2. **提交留言**
   - 在表单中输入昵称和祝福语
   - 点击提交按钮发送祝福
   - 提交成功后会显示确认提示

3. **查看留言**
   - 网站会随机展示部分用户留言
   - 所有留言经过敏感词过滤处理

## 项目结构
您部署该项目的时候，至少应该会有如下文件
```
newyear-countdown/
├── index.php         # 主程序文件
├── index.html        # 静态首页
├── 404.html          # 404错误页面
├── .htaccess         # Apache配置文件
├── .user.ini         # PHP配置文件
└── newyear_background.svg  # 背景图像
```

## 安全说明

- 所有用户输入都经过消毒处理，防止XSS攻击
- 使用参数化查询防止SQL注入
- 实现了敏感词过滤和URL检测功能
- 采用会话机制管理表单提交状态

## 贡献指南

1. Fork 本仓库
2. 创建特性分支 (`git checkout -b feature/AmazingFeature`)
3. 提交更改 (`git commit -m 'Add some AmazingFeature'`)
4. 推送到分支 (`git push origin feature/AmazingFeature`)
5. 开启Pull Request

## 许可证

本项目采用 MIT 许可证 - 详见 [LICENSE](LICENSE) 文件

## 致谢

感谢所有为本项目做出贡献的开发者和用户！

---
