/* eslint-env jest */

jest.useFakeTimers();

jest.mock('react-native-vector-icons/FontAwesome5', () => 'FontAwesome5');
<<<<<<< Updated upstream
jest.mock('react-native-image-picker', () => ({
  launchImageLibrary: jest.fn(),
}));
=======
jest.mock(
  'react-native-image-picker',
  () => ({
    launchImageLibrary: jest.fn(async () => ({ didCancel: true })),
  }),
  { virtual: true }
);
>>>>>>> Stashed changes
