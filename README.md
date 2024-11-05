# 注意事项

目前不需要防盗链，如果哪天防盗链了，需要在img标签加防盗链

```
referrerpolicy="no-referrer"
```

## 后台登录

后台默认登录用户名和密码：`admin/admin123`

修改密码：config.php

## Token配置说明

### 美团图床Token获取方式

- 注册https://czz.meituan.com/

- 发布视频，上传封面，注意在上传封面后，F12查找封面token即可，不用真的去发布视频

### Token配置步骤
1. 登录管理后台
2. 找到"Token配置"区域
3. 填入Token并保存
4. 保存成功后即可使用图床功能


## 后台管理

删除图片只会删除本地保存的，不会删除美团的，这是MJJ的常识吧



### 3. 图片备份方案

为了确保数据安全，我推荐使用 rclone 配合 Cloudflare R2 进行备份，放到计划任务里，以下是经过优化的备份命令：

```bash
rclone copy /www/wwwroot/1234.com/uploads r2:img/gtimg/uploads \
    -u -v -P \
    --transfers=20 \
    --ignore-errors \
    --buffer-size=64M \
    --check-first \
    --checkers=15 \
    --drive-acknowledge-abuse
```

参数说明：
- `/www/wwwroot/1234.com/uploads`：本地图片目录
- `r2:img/gtimg/uploads`：R2 存储路径（其中 `img` 为你的 R2 存储桶名称）
- 其他参数已优化为最佳性能配置

## 特别说明

⚠️ **使用限制**：目前不支持上传 avif 格式的图片


## 图片恢复方案

如果图床服务出现问题，可以通过以下步骤快速恢复：

1. 使用备份中的图片文件
2. 批量替换链接前缀 `https://img.meituan.net/video`
3. 把后缀都替换成.webp

这样的设计确保了即使服务中断，你的图片资源也不会丢失。

---