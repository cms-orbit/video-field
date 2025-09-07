# CMS-Orbit Video Field

비디오 업로드 및 관리를 위한 Orchid 필드 패키지입니다.

## 설치

```bash
composer require cms-orbit/video-field
```

## 기능

- **VideoSelector 필드**: 기존 비디오 목록에서 선택하거나 새 비디오를 업로드할 수 있는 필드
- **비디오 업로드**: 다중 파일 업로드 지원
- **자동 처리**: 업로드된 비디오에 대한 썸네일 생성, 스프라이트 생성, 인코딩 등 자동 처리
- **Queue 지원**: 백그라운드에서 비디오 처리 작업 수행

## 사용법

### VideoSelector 필드 사용

```php
use CmsOrbit\VideoField\Fields\VideoSelector\VideoSelectorFacade;

// 단일 비디오 선택
VideoSelectorFacade::make('video_id')
    ->title('Select Video')
    ->placeholder('Choose a video...')

// 다중 비디오 선택
VideoSelectorFacade::make('video_ids')
    ->title('Select Videos')
    ->multiple(true)
    ->placeholder('Choose videos...')
```

### 비디오 업로드

비디오는 VideoSelector 필드의 "Add New" 버튼을 통해 업로드할 수 있습니다.

### 비디오 처리

비디오가 업로드되면 자동으로 다음 작업들이 Queue를 통해 처리됩니다:

1. 썸네일 생성
2. 스프라이트 이미지 생성
3. 다양한 프로파일로 인코딩

## 설정

설정 파일을 발행하여 비디오 처리 옵션을 커스터마이징할 수 있습니다:

```bash
php artisan vendor:publish --tag=video-config
```

## 마이그레이션

데이터베이스 마이그레이션을 실행합니다:

```bash
php artisan migrate
```

## 언어 파일

언어 파일을 발행하여 다국어 지원을 설정할 수 있습니다:

```bash
php artisan vendor:publish --tag=video-lang
```

## 라이센스

MIT License
