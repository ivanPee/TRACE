/* eslint-env jest */

jest.useFakeTimers();

jest.mock('react-native-vector-icons/FontAwesome5', () => 'FontAwesome5');
jest.mock('react-native-image-picker', () => ({
  launchImageLibrary: jest.fn(),
}));
