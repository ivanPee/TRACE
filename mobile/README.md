# TRACE Mobile CLI

This mobile app is now structured as a React Native CLI project.

## What Changed
- Expo-style scripts were removed
- native `android` and `ios` folders were added
- the app now uses the React Native CLI entry flow
- your existing `src` screens, context, navigation, and map UI were kept

## Project Structure
- `App.js`
- `index.js`
- `android/`
- `ios/`
- `src/components`
- `src/context`
- `src/data`
- `src/navigation`
- `src/screens`
- `src/theme`

## Requirements
- Node.js 18 or newer
- Java JDK 17
- Android Studio
- Android SDK and emulator
- A running Android emulator or connected Android device

## Install Dependencies
From the `mobile` folder:

```bash
npm install
```

## Run In Debug Mode
Start Metro in one terminal:

```bash
npm run debug
```

In another terminal, build and run Android:

```bash
npm run android
```

If Metro is already running normally, you can also use:

```bash
npm start
```

## Typical CLI Workflow
Terminal 1:

```bash
npm start
```

Terminal 2:

```bash
npm run android
```

## Clean Restart
If the app or bundler gets stuck:

```bash
npm run debug
```

Then rerun:

```bash
npm run android
```

## If Android Build Fails
Try these checks:

1. Make sure Android Studio is installed
2. Make sure an emulator is running before `npm run android`
3. Make sure `ANDROID_HOME` is configured
4. Make sure platform tools and build tools are installed

## Current App Features
- role-based login demo
- parent registration
- driver registration
- student account creation
- ride booking
- driver ride status controls
- notifications
- chat
- profile
- live tracking UI with `react-native-maps`

## Current Limitation
The app is now CLI-based, but the business data is still mock-driven in:

- `src/context/AppContext.js`

The next coding step is backend integration with your PHP API.
